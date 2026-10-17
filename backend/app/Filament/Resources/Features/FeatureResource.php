<?php

namespace App\Filament\Resources\Features;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\DocumentType;
use App\Models\Feature;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class FeatureResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = Feature::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & compliance';

    protected static ?string $navigationLabel = 'Operator & vehicle features';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(60)->live(onBlur: true)->afterStateUpdated(fn ($set, $state, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Select::make('applies_to')->native(false)->options(['operator' => 'Operators', 'vehicle' => 'Vehicles'])->required(),
                Select::make('category')->native(false)->options(['capability' => 'Capability', 'language' => 'Language', 'training' => 'Training', 'vehicle' => 'Vehicle']),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('What clients see')->columns(2)->schema([
                Toggle::make('client_filterable')->label('Clients can request this')->columnSpanFull(),
                TextInput::make('client_label')->label('Label for clients')->placeholder('e.g. Armed operator'),
                Textarea::make('client_description')->rows(2),
                Select::make('surcharge_type')->native(false)->options(['none' => 'No surcharge', 'flat' => 'Flat fee per vehicle / officer', 'per_hour' => 'Per hour per vehicle / officer'])->default('none'),
                TextInput::make('surcharge_amount')->numeric()->prefix('R')->default(0),
            ]),
            Section::make('Compliance')->schema([
                Select::make('requiredDocumentTypes')->relationship('requiredDocumentTypes', 'name')->multiple()->preload()->native(false)
                    ->label('Only counts while these documents are valid')
                    ->helperText('e.g. "Armed" requires a valid firearm competency certificate and licence. If one lapses, the operator automatically loses this feature.'),
            ]),
            TextInput::make('sort')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('name')->weight('bold')->searchable(),
            TextColumn::make('applies_to')->badge()->color('gray'),
            TextColumn::make('category')->badge()->color('gray'),
            IconColumn::make('client_filterable')->boolean()->label('Client option'),
            TextColumn::make('surcharge_amount')->label('Surcharge')->formatStateUsing(fn ($state, $record) => $record->surcharge_type === 'none' ? '—' : rands($state).($record->surcharge_type === 'per_hour' ? '/h' : '')),
            TextColumn::make('requiredDocumentTypes.name')->badge()->color('warning')->label('Requires'),
            TextColumn::make('operators_count')->counts('operators')->label('Operators'),
            IconColumn::make('is_active')->boolean(),
        ])->filters([
            SelectFilter::make('applies_to')->options(['operator' => 'Operators', 'vehicle' => 'Vehicles']),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
