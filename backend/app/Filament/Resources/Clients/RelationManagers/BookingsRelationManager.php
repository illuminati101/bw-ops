<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            TextColumn::make('reference')->weight('bold'),
            TextColumn::make('service.name'),
            TextColumn::make('starts_at')->dateTime('D j M Y H:i')->label('When'),
            TextColumn::make('status')->badge(),
            TextColumn::make('quoted_total')->money('ZAR')->label('Quote'),
        ])->recordActions([
            Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn ($record) => \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $record])),
        ]);
    }
}
