<?php

namespace App\Filament\Resources\Operators\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RosterShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'rosterShifts';

    protected static ?string $title = 'Roster';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DateTimePicker::make('starts_at')->required()->seconds(false)->native(false),
            DateTimePicker::make('ends_at')->required()->seconds(false)->native(false)->after('starts_at'),
            Select::make('status')->options(['approved' => 'Approved', 'requested' => 'Requested', 'rejected' => 'Rejected'])->default('approved')->native(false),
            TextInput::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            TextColumn::make('starts_at')->dateTime('D j M H:i')->label('From'),
            TextColumn::make('ends_at')->dateTime('D j M H:i')->label('To'),
            TextColumn::make('status')->badge()->color(fn ($state) => ['approved' => 'success', 'requested' => 'warning'][$state] ?? 'gray'),
            TextColumn::make('notes'),
        ])->headerActions([CreateAction::make()])->recordActions([
            Action::make('approve')->icon('heroicon-o-check')->color('success')->visible(fn ($record) => $record->status === 'requested')->action(fn ($record) => $record->update(['status' => 'approved'])),
            EditAction::make(), DeleteAction::make(),
        ]);
    }
}
