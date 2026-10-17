<?php

namespace App\Filament\Resources\Extras;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\DraftColumns;
use App\Models\Extra;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ExtraResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = Extra::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Services & pricing';

    protected static ?string $navigationLabel = 'Add-ons';

    protected static ?string $modelLabel = 'add-on';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)->afterStateUpdated(fn ($set, $state, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Select::make('pricing')->native(false)->options(['flat' => 'Flat fee', 'per_hour' => 'Per hour', 'percent' => 'Percentage of the booking'])->required(),
                TextInput::make('amount')->numeric()->required()->helperText('Rand amount, or % for percentage pricing.'),
            ]),
            Textarea::make('description')->rows(2),
            CheckboxList::make('services')->relationship('services', 'name')->columns(3)->label('Available on'),
            TextInput::make('sort')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('name')->weight('bold')->description(fn ($record) => $record->description),
            TextColumn::make('pricing')->badge()->color('gray'),
            TextColumn::make('amount')->formatStateUsing(fn ($state, $record) => $record->pricing === 'percent' ? $state.'%' : rands($state)),
            TextColumn::make('services.name')->badge()->color('gray')->label('Services'),
            DraftColumns::status(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExtras::route('/'),
            'create' => Pages\CreateExtra::route('/create'),
            'edit' => Pages\EditExtra::route('/{record}/edit'),
        ];
    }
}
