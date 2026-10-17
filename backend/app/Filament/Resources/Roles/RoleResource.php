<?php

namespace App\Filament\Resources\Roles;

use BackedEnum;
use Database\Seeders\RolesSeeder;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?int $navigationSort = 2;

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
        return static::canViewAny() && $record->name !== 'Owner';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! array_key_exists($record->name, RolesSeeder::ROLES);
    }

    public static function form(Schema $schema): Schema
    {
        $groups = collect(RolesSeeder::PERMISSIONS)->groupBy(fn ($label, $key) => explode('.', $key)[0], true);

        return $schema->components([
            TextInput::make('name')->required()->unique(ignoreRecord: true),
            CheckboxList::make('permissions')->relationship('permissions', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => RolesSeeder::PERMISSIONS[$record->name] ?? $record->name)
                ->columns(2)->bulkToggleable()->searchable()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold'),
            TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
            TextColumn::make('users_count')->counts('users')->label('Staff'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
