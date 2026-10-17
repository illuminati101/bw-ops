<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Enums\AssignmentStatus;
use App\Services\Dispatch\DispatchService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Operators';

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['operator.user', 'vehicle', 'shift']))
            ->poll('10s')
            ->columns([
                TextColumn::make('operator.call_sign')->label('Call sign')->weight('bold'),
                TextColumn::make('operator.user.name')->label('Name')->description(fn ($record) => $record->operator?->user?->phone),
                TextColumn::make('role')->formatStateUsing(fn ($state, $record) => $record->roleLabel()),
                TextColumn::make('shift')->label('Shift')->state(fn ($record) => $record->shift?->label())->placeholder('—'),
                TextColumn::make('vehicle')->state(fn ($record) => $record->vehicle?->label())->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('arrived_at')->label('Arrived')->time('H:i')->placeholder('—'),
                TextColumn::make('payout_amount')->label('Payout')->money('ZAR')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('remove')->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn ($record) => in_array($record->status, AssignmentStatus::live(), true) && auth()->user()->can('bookings.assign'))
                    ->requiresConfirmation()->modalDescription('The operator is notified and the slot reopens.')
                    ->action(fn ($record) => app(DispatchService::class)->removeAssignment($record, auth()->user())),
            ]);
    }
}
