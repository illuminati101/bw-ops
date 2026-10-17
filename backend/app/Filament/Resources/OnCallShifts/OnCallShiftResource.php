<?php

namespace App\Filament\Resources\OnCallShifts;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\OnCallShift;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OnCallShiftResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'control_room';

    protected static ?string $model = OnCallShift::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone-arrow-down-left';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'On-call roster';

    protected static ?int $navigationSort = 4;

    protected static function allowed(string $action): bool
    {
        $u = auth()->user();

        return $u && ($u->hasRole('Owner') || $u->can($action === 'view' ? 'control_room.view' : 'settings.update') || $u->hasRole('Ops Manager'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('user_id')->label('Staff member')->required()->native(false)->searchable()
                ->options(fn () => User::query()->where('type', 'staff')->where('status', 'active')->get()->mapWithKeys(fn ($u) => [$u->id => $u->name.($u->phone ? '' : ' (no mobile number!)')])),
            Select::make('priority')->options([1 => 'Primary', 2 => 'Backup'])->default(1)->required()->native(false),
            DateTimePicker::make('starts_at')->required()->seconds(false)->native(false),
            DateTimePicker::make('ends_at')->required()->seconds(false)->native(false)->after('starts_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at')->modifyQueryUsing(fn ($query) => $query->where('ends_at', '>=', now()->subDay()))
            ->columns([
                TextColumn::make('user.name')->label('Staff')->weight('bold')->description(fn ($record) => $record->user?->phone ?: 'No mobile: cannot receive SMS or calls'),
                TextColumn::make('priority')->badge()->formatStateUsing(fn ($state) => $state == 1 ? 'Primary' : 'Backup'),
                TextColumn::make('starts_at')->dateTime('D j M H:i'),
                TextColumn::make('ends_at')->dateTime('D j M H:i'),
                TextColumn::make('now')->label('')->state(fn ($record) => $record->starts_at->isPast() && $record->ends_at->isFuture() ? 'On call now' : null)->badge()->color('success'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnCallShifts::route('/'),
            'create' => Pages\CreateOnCallShift::route('/create'),
            'edit' => Pages\EditOnCallShift::route('/{record}/edit'),
        ];
    }
}
