<?php

namespace App\Filament\Resources\SurchargeRules;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\SurchargeRule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class SurchargeRuleResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = SurchargeRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-moon';

    protected static string|UnitEnum|null $navigationGroup = 'Services & pricing';

    protected static ?string $navigationLabel = 'Surcharges';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required(),
            Select::make('kind')->native(false)->options(['night' => 'Night (time window)', 'weekend' => 'Weekend', 'holiday' => 'Public holiday'])->required()->live(),
            TimePicker::make('starts_at')->seconds(false)->visible(fn ($get) => $get('kind') === 'night'),
            TimePicker::make('ends_at')->seconds(false)->visible(fn ($get) => $get('kind') === 'night'),
            TextInput::make('percent')->numeric()->suffix('%')->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold')->description('Only the single highest applicable surcharge is applied.'),
            TextColumn::make('kind')->badge()->color('gray'),
            TextColumn::make('percent')->suffix('%'),
            ToggleColumn::make('is_active')->label('Active'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurchargeRules::route('/'),
            'create' => Pages\CreateSurchargeRule::route('/create'),
            'edit' => Pages\EditSurchargeRule::route('/{record}/edit'),
        ];
    }
}
