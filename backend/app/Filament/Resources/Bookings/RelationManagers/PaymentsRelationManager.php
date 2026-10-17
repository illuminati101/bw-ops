<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->dateTime('D j M H:i')->label('Time'),
            TextColumn::make('type')->badge()->color('gray'),
            TextColumn::make('amount')->money('ZAR'),
            TextColumn::make('gateway')->badge()->color('gray'),
            TextColumn::make('status')->badge()->color(fn ($state) => ['success' => 'success', 'failed' => 'danger'][$state] ?? 'warning'),
            TextColumn::make('description')->wrap(),
            TextColumn::make('meta.message')->label('Note')->wrap()->placeholder('—'),
        ]);
    }
}
