<?php

namespace App\Filament\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Concerns\ChecksPermissions;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class BookingResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'bookings';

    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'client.name', 'client.phone', 'principal_name'];
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Booking::query()->whereIn('status', [BookingStatus::PendingApproval->value, BookingStatus::QuoteRequested->value])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for approval or a quote';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        // Edit form: notes and principal details only. Everything else changes through actions on the booking page.
        return $schema->columns(2)->components([
            Section::make('Principal')->columns(2)->schema([
                TextInput::make('principal_name'),
                TextInput::make('principal_phone')->tel()->dehydrateStateUsing(fn ($state) => \App\Support\PhoneNumber::normalize($state)),
            ]),
            Section::make('Notes')->schema([
                Textarea::make('operator_notes')->label('Briefing for operators')->rows(3)->helperText('Shown to assigned operators in the Crew app while the job is live.'),
                Textarea::make('internal_notes')->label('Internal notes')->rows(3)->helperText('Staff only.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(3)->schema([
                TextEntry::make('status')->badge(),
                TextEntry::make('mode')->badge()->color('gray')->formatStateUsing(fn ($state) => $state === 'on_demand' ? 'On demand' : 'Advance'),
                TextEntry::make('structure')->badge()->color('gray'),
                TextEntry::make('service.name')->label('Service'),
                TextEntry::make('starts_at')->label('Starts')->dateTime('D j M Y, H:i'),
                TextEntry::make('duration_minutes')->label('Duration')->formatStateUsing(fn ($state) => intdiv($state, 60).'h '.($state % 60 ? ($state % 60).'m' : '')),
                TextEntry::make('pickup_address')->label('Pickup')->columnSpan(2)->url(fn ($record) => "https://www.google.com/maps/search/?api=1&query={$record->pickup_lat},{$record->pickup_lng}", true),
                TextEntry::make('estimated_km')->label('Estimated distance')->suffix(' km'),
                TextEntry::make('dropoff_address')->label('Drop-off')->columnSpan(2)->placeholder('—'),
                TextEntry::make('passengers'),
                TextEntry::make('stops_list')->label('Stops')->columnSpanFull()->state(fn ($record) => $record->stops->map(fn ($s) => $s->sequence.'. '.$s->address.($s->wait_minutes ? " (wait {$s->wait_minutes} min)" : ''))->implode("\n") ?: null)->placeholder('—'),
                TextEntry::make('requirements')->label('Requirements')->badge()->color('primary')->state(fn ($record) => \App\Models\Feature::query()->whereIn('id', $record->feature_ids ?? [])->pluck('name')->all())->placeholder('None'),
                TextEntry::make('guards_count')->label('Officers'),
                TextEntry::make('vehicles_count')->label('Vehicles'),
                TextEntry::make('client_notes')->label('Client notes')->columnSpanFull()->placeholder('—'),
                TextEntry::make('operator_notes')->label('Operator briefing')->columnSpanFull()->placeholder('—'),
                TextEntry::make('internal_notes')->label('Internal notes')->columnSpanFull()->placeholder('—'),
            ]),
            Grid::make(1)->schema([
                Section::make('Client')->schema([
                    TextEntry::make('client.name')->label('Account')->url(fn ($record) => \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $record->client_id])),
                    TextEntry::make('client.phone')->label('Phone')->copyable(),
                    TextEntry::make('company.name')->label('Company')->placeholder('—'),
                    TextEntry::make('principal_name')->label('Principal')->placeholder('Same as client')->helperText(fn ($record) => $record->principal_phone),
                    TextEntry::make('arrival_pin')->label('Arrival PIN')->helperText(fn ($record) => $record->pin_verified_at ? 'Verified '.$record->pin_verified_at->format('H:i') : 'Not yet verified'),
                    TextEntry::make('reconfirm')->label('Reconfirmation')->state(fn ($record) => $record->reconfirmed_at ? 'Reconfirmed '.$record->reconfirmed_at->format('D H:i') : ($record->reconfirm_due_at ? 'Due by '.$record->reconfirm_due_at->format('D j M H:i') : 'Not required')),
                ]),
                Section::make('Money')->schema([
                    TextEntry::make('quote_lines')->hiddenLabel()->state(fn ($record) => new HtmlString(
                        '<table style="width:100%;font-size:.85rem">'.collect($record->quote_breakdown['lines'] ?? [])->map(fn ($l) => '<tr><td style="padding:2px 0;color:#a1a1aa">'.e($l['label']).'</td><td style="text-align:right">'.rands($l['amount']).'</td></tr>')->implode('')
                        .(! empty($record->quote_breakdown['surcharge']) ? '<tr><td style="color:#a1a1aa">'.e($record->quote_breakdown['surcharge']['label']).'</td><td style="text-align:right">'.rands($record->quote_breakdown['surcharge']['amount']).'</td></tr>' : '')
                        .'<tr><td style="padding-top:6px;font-weight:700">Quote</td><td style="text-align:right;font-weight:700;color:#C9A86A">'.($record->status === BookingStatus::QuoteRequested ? 'Awaiting quote' : rands($record->quoted_total)).'</td></tr>'
                        .($record->final_total ? '<tr><td style="font-weight:700">Final</td><td style="text-align:right;font-weight:700">'.rands($record->final_total).'</td></tr>' : '')
                        .($record->cancellation_fee ? '<tr><td>Cancellation / no-show fee</td><td style="text-align:right">'.rands($record->cancellation_fee).'</td></tr>' : '')
                        .'</table>'
                    )),
                    TextEntry::make('overtime_rate')->label('Overtime rate')->formatStateUsing(fn ($state) => rands($state).' / hour'),
                    TextEntry::make('payment_method')->label('Payment')->formatStateUsing(fn ($state, $record) => ['card' => 'Saved card', 'card_machine' => 'Card machine', 'eft' => 'EFT invoice'][$state] ?? $state)->helperText(fn ($record) => $record->paymentMethodRecord?->label()),
                    TextEntry::make('payment_status')->badge()->color(fn ($state) => ['paid' => 'success', 'failed' => 'danger', 'held' => 'info'][$state] ?? 'gray'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'service', 'activeAssignments.operator']))
            ->defaultSort('starts_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('reference')->weight('bold')->searchable()->copyable(),
                TextColumn::make('starts_at')->label('When')->dateTime('D j M, H:i')->sortable()->description(fn ($record) => $record->mode === 'on_demand' ? 'On demand' : null),
                TextColumn::make('client.name')->label('Client')->searchable()->description(fn ($record) => $record->principal_name ? 'For '.$record->principal_name : null),
                TextColumn::make('service.name')->label('Service')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('operators')->label('Operators')->state(fn ($record) => $record->activeAssignments->map(fn ($a) => $a->operator?->call_sign ?? '?')->implode(', ') ?: null)->placeholder('—'),
                TextColumn::make('quoted_total')->label('Quote')->money('ZAR')->sortable(),
                TextColumn::make('payment_status')->label('Payment')->badge()->color(fn ($state) => ['paid' => 'success', 'failed' => 'danger', 'held' => 'info', 'invoiced' => 'primary'][$state] ?? 'gray')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(BookingStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))->multiple(),
                SelectFilter::make('service_id')->relationship('service', 'name')->label('Service'),
                SelectFilter::make('mode')->options(['advance' => 'Advance', 'on_demand' => 'On demand']),
                Filter::make('today')->label('Today')->query(fn ($query) => $query->whereDate('starts_at', today())),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AssignmentsRelationManager::class,
            RelationManagers\EventsRelationManager::class,
            RelationManagers\MessagesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
