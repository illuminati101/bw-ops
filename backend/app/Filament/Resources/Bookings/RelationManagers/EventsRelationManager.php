<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Timeline';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->poll('10s')->paginated([25, 50])->columns([
            TextColumn::make('created_at')->label('Time')->dateTime('D j M H:i:s'),
            TextColumn::make('summary')->wrap(),
            TextColumn::make('actor.name')->label('By')->placeholder('System'),
        ]);
    }
}
