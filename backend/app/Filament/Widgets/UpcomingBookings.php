<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingBookings extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Next 48 hours';

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->with(['client', 'service', 'activeAssignments.operator'])
                ->whereBetween('starts_at', [now()->subHours(2), now()->addHours(48)])
                ->whereNotIn('status', array_map(fn ($s) => $s->value, BookingStatus::closed()))->orderBy('starts_at'))
            ->poll('30s')
            ->paginated([10])
            ->columns([
                TextColumn::make('starts_at')->dateTime('D H:i')->label('When'),
                TextColumn::make('reference')->weight('bold'),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('service.name')->label('Service'),
                TextColumn::make('status')->badge(),
                TextColumn::make('ops')->label('Operators')->state(fn ($record) => $record->activeAssignments->map(fn ($a) => $a->operator?->call_sign)->implode(', ') ?: null)->placeholder('None yet')->color(fn ($state) => $state ? null : 'danger'),
            ])
            ->recordActions([Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn ($record) => BookingResource::getUrl('view', ['record' => $record]))]);
    }
}
