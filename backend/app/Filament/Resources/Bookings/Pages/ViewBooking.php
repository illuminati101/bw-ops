<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingShift;
use App\Models\OperatorProfile;
use App\Models\Vehicle;
use App\Services\Booking\BookingService;
use App\Services\Booking\JobService;
use App\Services\Dispatch\DispatchService;
use App\Services\Payments\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected $listeners = ['bw-refresh' => '$refresh'];

    public function getTitle(): string
    {
        return $this->getRecord()->reference.' · '.$this->getRecord()->service->name;
    }

    private function can(string $p): bool
    {
        return auth()->user()->can($p);
    }

    /** Run a domain action and show its validation message as a notification instead of crashing. */
    private function attempt(callable $fn, string $success): void
    {
        try {
            $fn();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Not possible')->body(collect($e->errors())->flatten()->first())->danger()->send();
        }
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        $b = fn () => $this->getRecord();

        return [
            Action::make('approve')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn () => $b()->status === BookingStatus::PendingApproval && $this->can('bookings.approve'))
                ->requiresConfirmation()->modalDescription(fn () => 'Confirm '.$b()->reference.' for '.$b()->client->name.'. The client is notified immediately.')
                ->action(fn () => $this->attempt(fn () => app(BookingService::class)->approve($b(), auth()->user()), 'Booking approved')),

            Action::make('quote')->label('Send quote')->icon('heroicon-o-currency-dollar')->color('primary')
                ->visible(fn () => in_array($b()->status, [BookingStatus::QuoteRequested, BookingStatus::Quoted, BookingStatus::PendingApproval], true) && $this->can('bookings.approve'))
                ->schema([
                    Placeholder::make('estimate')->label('Calculated from rate card')->content(fn () => rands($b()->quote_breakdown['total'] ?? 0).' (guide only)'),
                    TextInput::make('total')->numeric()->prefix('R')->required()->default(fn () => $b()->quote_breakdown['total'] ?? null),
                    Textarea::make('note')->label('Note to the client')->rows(3)->placeholder('Includes a venue walk-through on the day before.'),
                ])
                ->action(fn (array $data) => $this->attempt(fn () => app(BookingService::class)->sendQuote($b(), (float) $data['total'], $data['note'] ?? null, auth()->user()), 'Quote sent to the client')),

            Action::make('assign')->label('Assign operator')->icon('heroicon-o-user-plus')->color('primary')
                ->visible(fn () => ! $b()->status->isClosed() && ! in_array($b()->status, [BookingStatus::QuoteRequested, BookingStatus::Quoted], true) && $this->can('bookings.assign'))
                ->schema(fn () => $this->assignForm())
                ->action(function (array $data) use ($b) {
                    $this->attempt(function () use ($data, $b) {
                        $op = OperatorProfile::findOrFail($data['operator_id']);
                        $vehicle = ! empty($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;
                        $shift = ! empty($data['shift_id']) ? BookingShift::find($data['shift_id']) : null;
                        app(DispatchService::class)->assign($b(), $op, $data['role'], $vehicle, auth()->user(), $shift, (bool) ($data['pre_accepted'] ?? false));
                    }, 'Operator assigned');
                }),

            ActionGroup::make([
                Action::make('redispatch')->label('Retry automatic dispatch')->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $b()->status === BookingStatus::Searching && $this->can('bookings.assign'))
                    ->action(function () use ($b) {
                        $b()->update(['escalated_at' => null]);
                        foreach (array_unique(app(DispatchService::class)->openRoles($b())) as $role) {
                            app(DispatchService::class)->offerNext($b(), $role);
                        }
                        Notification::make()->title('Dispatch restarted')->success()->send();
                    }),
                Action::make('reconfirmed')->label('Client reconfirmed by phone')->icon('heroicon-o-phone')
                    ->visible(fn () => $b()->reconfirm_due_at && ! $b()->reconfirmed_at && ! $b()->status->isClosed())
                    ->action(fn () => $this->attempt(fn () => app(BookingService::class)->reconfirm($b(), auth()->user()), 'Marked as reconfirmed')),
                Action::make('complete')->label('Complete job now')->icon('heroicon-o-flag')->color('success')
                    ->visible(fn () => in_array($b()->status, [BookingStatus::InProgress, BookingStatus::Arrived, BookingStatus::EnRoute], true) && $this->can('bookings.update'))
                    ->requiresConfirmation()->modalDescription('Use if the operator could not complete it in the app. Overtime is calculated from the start time, and the client is charged.')
                    ->action(function () use ($b) {
                        $this->attempt(function () use ($b) {
                            foreach ($b()->activeAssignments()->get() as $a) {
                                $a->update(['status' => AssignmentStatus::Done, 'finished_at' => now()]);
                            }
                            app(JobService::class)->finish($b());
                        }, 'Job completed');
                    }),
                Action::make('noshow')->label('Record no-show')->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn () => in_array($b()->status, [BookingStatus::Arrived, BookingStatus::Assigned, BookingStatus::EnRoute], true) && $this->can('bookings.cancel'))
                    ->requiresConfirmation()->modalDescription(fn () => 'Charges the no-show fee ('.rands(app(\App\Services\Pricing\PricingService::class)->noShowFee($b()->quoted_total)).') and adds a strike.')
                    ->action(fn () => $this->attempt(fn () => app(BookingService::class)->recordNoShow($b(), auth()->user()), 'No-show recorded')),
                Action::make('paid')->label('Mark as paid')->icon('heroicon-o-banknotes')
                    ->visible(fn () => in_array($b()->payment_status, ['unpaid', 'failed'], true) && $b()->status === BookingStatus::Completed && $this->can('finance.update'))
                    ->schema([TextInput::make('reference')->label('Payment reference')])
                    ->action(fn (array $data) => $this->attempt(fn () => app(PaymentService::class)->markCardMachinePaid($b(), $data['reference'] ?? null), 'Marked as paid')),
                Action::make('decline')->label('Decline booking')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn () => in_array($b()->status, [BookingStatus::PendingApproval, BookingStatus::QuoteRequested, BookingStatus::Quoted], true) && $this->can('bookings.approve'))
                    ->schema([Textarea::make('reason')->label('Reason (sent to the client)')->rows(2)])
                    ->action(fn (array $data) => $this->attempt(fn () => app(BookingService::class)->decline($b(), auth()->user(), $data['reason'] ?? null), 'Booking declined')),
                Action::make('cancel')->label('Cancel booking')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn () => $b()->status->isCancellable() && $this->can('bookings.cancel'))
                    ->schema([
                        Placeholder::make('fee')->label('Fee under the policy')->content(fn () => ($f = app(BookingService::class)->cancellationFee($b()))['fee'] > 0 ? rands($f['fee']).' · '.$f['reason'] : 'None · '.$f['reason']),
                        Toggle::make('waive')->label('Waive the fee (cancelled by us / death or hospitalisation / goodwill)')->default(true),
                        Textarea::make('reason')->rows(2)->required(),
                    ])
                    ->action(fn (array $data) => $this->attempt(fn () => app(BookingService::class)->cancel($b(), auth()->user(), $data['reason'], (bool) $data['waive']), 'Booking cancelled')),
                EditAction::make()->label('Edit notes')->icon('heroicon-o-pencil'),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    private function assignForm(): array
    {
        $booking = $this->getRecord();
        $dispatch = app(DispatchService::class);
        $open = $dispatch->openRoles($booking);
        $roles = collect($booking->shifts()->exists() ? $dispatch->requiredRoles($booking) : ($open ?: $dispatch->requiredRoles($booking)))->unique()
            ->mapWithKeys(fn ($r) => [$r => $r === 'driver' ? 'Chauffeur' : 'Protection officer'])->all() + ['lead' => 'Detail lead'];

        return [
            Select::make('role')->options($roles)->required()->native(false)->live()->default(array_key_first($roles)),
            Select::make('shift_id')->label('Shift')->native(false)->visible(fn () => $booking->shifts()->exists())
                ->options(fn () => $booking->shifts->mapWithKeys(fn ($s) => [$s->id => $s->label()]))->required(fn () => $booking->shifts()->exists()),
            Select::make('operator_id')->label('Operator')->required()->native(false)->searchable()->live()
                ->options(function ($get) use ($booking, $dispatch) {
                    $role = $get('role') === 'lead' ? 'guard' : ($get('role') ?? 'driver');

                    return $dispatch->candidates($booking, $role, false)->mapWithKeys(fn ($c) => [
                        $c['operator']->id => $c['operator']->label()
                            .($c['operator']->is_online ? ' · online' : ' · offline')
                            .($c['distance_km'] !== null ? ' · '.number_format($c['distance_km'], 1).' km' : '')
                            .($c['operator']->id === $booking->preferred_operator_id ? ' · ★ preferred' : ''),
                    ])->all();
                })
                ->helperText('Only operators who are free, compliant and meet the booking\'s requirements are listed.'),
            Select::make('vehicle_id')->label('Vehicle')->native(false)->visible(fn ($get) => $get('role') === 'driver')
                ->options(fn () => Vehicle::query()->where('status', 'active')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label().' · '.$v->classLabel()]))
                ->default(fn ($get) => OperatorProfile::find($get('operator_id'))?->current_vehicle_id)
                ->helperText('Defaults to the operator\'s current vehicle.'),
            Toggle::make('pre_accepted')->label('Already confirmed with the operator (skip acceptance in the app)'),
        ];
    }
}
