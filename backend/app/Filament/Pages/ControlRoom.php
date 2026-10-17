<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\OperatorProfile;
use App\Models\SosAlert;
use App\Services\Booking\BookingService;
use App\Services\Safety\SosService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ControlRoom extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Control room';

    protected string $view = 'filament.pages.control-room';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('control_room.view');
    }

    public function getViewData(): array
    {
        $v = fn (array $s) => array_map(fn ($x) => $x->value, $s);

        return [
            'sos' => SosAlert::query()->whereIn('status', ['open', 'acknowledged'])->with(['user', 'booking'])->latest()->get(),
            'pending' => Booking::query()->where('status', BookingStatus::PendingApproval->value)->with(['client', 'service'])->orderBy('starts_at')->limit(15)->get(),
            'searching' => Booking::query()->where('status', BookingStatus::Searching->value)->with(['client', 'service'])->orderBy('created_at')->get(),
            'live' => Booking::query()->whereIn('status', $v([BookingStatus::Assigned, BookingStatus::EnRoute, BookingStatus::Arrived, BookingStatus::InProgress]))
                ->where(fn ($q) => $q->where('status', '!=', BookingStatus::Assigned->value)->orWhere('starts_at', '<=', now()->addHours(2)))
                ->with(['client', 'service', 'activeAssignments.operator'])->orderBy('starts_at')->get(),
            'notReconfirmed' => Booking::query()->whereIn('status', $v([BookingStatus::Confirmed, BookingStatus::Assigned]))->whereNotNull('reconfirm_alerted_at')->whereNull('reconfirmed_at')->with('client')->get(),
            'unassigned' => Booking::query()->where('status', BookingStatus::Confirmed->value)->where('starts_at', '<=', now()->addHours(24))->with(['client', 'service'])->orderBy('starts_at')->get(),
            'online' => OperatorProfile::query()->where('is_online', true)->count(),
            'canRespond' => auth()->user()->can('control_room.respond'),
            'canApprove' => auth()->user()->can('bookings.approve'),
        ];
    }

    public function acknowledge(int $id): void
    {
        abort_unless(auth()->user()->can('control_room.respond'), 403);
        app(SosService::class)->acknowledge(SosAlert::findOrFail($id), auth()->user());
        Notification::make()->title('Acknowledged: escalation stopped')->success()->send();
    }

    public function resolve(int $id, string $outcome): void
    {
        abort_unless(auth()->user()->can('control_room.respond'), 403);
        app(SosService::class)->resolve(SosAlert::findOrFail($id), auth()->user(), $outcome, 'Closed from control room');
        Notification::make()->title('Alert closed')->success()->send();
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()->can('bookings.approve'), 403);
        try {
            app(BookingService::class)->approve(Booking::findOrFail($id), auth()->user());
            Notification::make()->title('Approved')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
