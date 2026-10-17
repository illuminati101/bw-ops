<?php

namespace App\Filament\Resources\PublicHolidays;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\PublicHoliday;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PublicHolidayResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = PublicHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Services & pricing';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->required()->unique(ignoreRecord: true)->native(false),
            TextInput::make('name')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date')->modifyQueryUsing(fn ($query) => $query->whereDate('date', '>=', now()->subMonth()))
            ->columns([
                TextColumn::make('date')->date('D j M Y')->sortable(),
                TextColumn::make('name'),
            ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicHolidays::route('/'),
            'create' => Pages\CreatePublicHoliday::route('/create'),
            'edit' => Pages\EditPublicHoliday::route('/{record}/edit'),
        ];
    }
}
