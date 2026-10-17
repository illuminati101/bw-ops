<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StrikesRelationManager extends RelationManager
{
    protected static string $relationship = 'strikes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('reason')->required()]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('reason'),
            TextColumn::make('booking.reference')->label('Booking'),
            TextColumn::make('created_at')->dateTime('j M Y'),
            TextColumn::make('voided_at')->label('Voided')->dateTime('j M Y')->placeholder('Active'),
        ])->headerActions([CreateAction::make()->label('Add strike')])->recordActions([
            Action::make('void')->label('Remove strike')->icon('heroicon-o-arrow-uturn-left')->color('gray')->visible(fn ($record) => ! $record->voided_at)
                ->requiresConfirmation()->action(fn ($record) => $record->update(['voided_at' => now(), 'voided_by' => auth()->id()])),
        ]);
    }
}
