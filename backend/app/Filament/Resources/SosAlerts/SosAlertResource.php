<?php

namespace App\Filament\Resources\SosAlerts;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\SosAlert;
use App\Services\Safety\SosService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SosAlertResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'control_room';

    protected static ?string $model = SosAlert::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'SOS alerts';

    protected static ?string $modelLabel = 'SOS alert';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = SosAlert::query()->whereIn('status', ['open', 'acknowledged'])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->poll('5s')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'booking', 'acknowledger']))
            ->columns([
                TextColumn::make('created_at')->label('Raised')->dateTime('D j M H:i:s')->description(fn ($record) => $record->created_at->diffForHumans()),
                TextColumn::make('user.name')->label('Who')->weight('bold')->description(fn ($record) => ucfirst($record->raised_by_role).' · '.$record->user?->phone),
                TextColumn::make('booking.reference')->label('Booking')->placeholder('—')
                    ->url(fn ($record) => $record->booking ? \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $record->booking]) : null),
                TextColumn::make('location')->state(fn ($record) => $record->lat ? number_format($record->lat, 5).', '.number_format($record->lng, 5) : 'Unknown')
                    ->url(fn ($record) => $record->lat ? "https://www.google.com/maps/search/?api=1&query={$record->lat},{$record->lng}" : null, true),
                TextColumn::make('status')->badge()->color(fn ($state) => ['open' => 'danger', 'acknowledged' => 'warning', 'resolved' => 'success'][$state] ?? 'gray')
                    ->extraAttributes(fn ($record) => $record->status === 'open' ? ['class' => 'bw-sos-flash'] : []),
                TextColumn::make('escalation_level')->label('Escalation')->formatStateUsing(fn ($state) => ['On screen', 'SMS', 'Phone call', 'Phone call + backup'][$state] ?? "Level {$state}"),
                TextColumn::make('acknowledger.name')->label('Handled by')->placeholder('—'),
                TextColumn::make('notes')->wrap()->limit(80)->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(['open' => 'Open', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved', 'false_alarm' => 'False alarm', 'cancelled' => 'Cancelled by user'])])
            ->recordActions([
                Action::make('ack')->label('Acknowledge')->icon('heroicon-o-hand-raised')->color('warning')
                    ->visible(fn ($record) => $record->status === 'open' && auth()->user()->can('control_room.respond'))
                    ->action(fn ($record) => app(SosService::class)->acknowledge($record, auth()->user())),
                Action::make('resolve')->label('Close')->icon('heroicon-o-check')->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['open', 'acknowledged'], true) && auth()->user()->can('control_room.respond'))
                    ->schema([
                        Select::make('outcome')->options(['resolved' => 'Resolved', 'false_alarm' => 'False alarm'])->required()->native(false),
                        Textarea::make('notes')->label('What happened')->required()->rows(3),
                    ])
                    ->action(fn ($record, array $data) => app(SosService::class)->resolve($record, auth()->user(), $data['outcome'], $data['notes'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSosAlerts::route('/')];
    }
}
