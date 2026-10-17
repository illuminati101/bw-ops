<?php

namespace App\Filament\Resources\Operators\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Jobs';

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('booking.service')->latest('id'))
            ->columns([
                TextColumn::make('booking.reference')->label('Booking')->weight('bold'),
                TextColumn::make('booking.service.name')->label('Service'),
                TextColumn::make('booking.starts_at')->label('When')->dateTime('D j M H:i'),
                TextColumn::make('role')->badge()->color('gray'),
                TextColumn::make('status')->badge(),
                TextColumn::make('payout_amount')->money('ZAR')->label('Payout')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn ($record) => \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $record->booking])),
            ]);
    }
}
