<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Authorised bookers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')->options(['admin' => 'Admin (sees all company bookings)', 'booker' => 'Booker', 'passenger' => 'Passenger'])->required()->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold'),
            TextColumn::make('phone'),
            TextColumn::make('email'),
            TextColumn::make('pivot.role')->label('Role')->badge(),
        ])->headerActions([
            AttachAction::make()->recordSelectOptionsQuery(fn ($query) => $query->where('type', 'client'))->recordSelectSearchColumns(['name', 'phone', 'email'])
                ->schema(fn (AttachAction $action) => [$action->getRecordSelect(), Select::make('role')->options(['admin' => 'Admin', 'booker' => 'Booker', 'passenger' => 'Passenger'])->default('booker')->required()]),
        ])->recordActions([EditAction::make(), DetachAction::make()->label('Remove')]);
    }
}
