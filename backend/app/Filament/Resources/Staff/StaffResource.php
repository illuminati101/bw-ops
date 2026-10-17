<?php

namespace App\Filament\Resources\Staff;

use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Staff accounts';

    protected static ?string $modelLabel = 'staff member';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('staff.manage');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record->id !== auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'staff');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->tel()->unique(ignoreRecord: true)->helperText('Needed to receive SOS escalation SMS and calls when on call.')
                ->dehydrateStateUsing(fn ($state) => \App\Support\PhoneNumber::normalize($state)),
            Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->default('active')->native(false),
            Select::make('roles')->relationship('roles', 'name')->multiple()->preload()->native(false)->required(),
            TextInput::make('password')->password()->revealable()->required(fn ($record) => ! $record)->dehydrated(fn ($state) => filled($state))
                ->helperText(fn ($record) => $record ? 'Leave blank to keep the current password.' : 'They can change it from their profile.')->minLength(10),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold'),
            TextColumn::make('email'),
            TextColumn::make('phone')->placeholder('—'),
            TextColumn::make('roles.name')->badge(),
            IconColumn::make('mfa')->label('2FA')->boolean()->state(fn ($record) => filled($record->app_authentication_secret)),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
