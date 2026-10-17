<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Vehicle;
use App\Services\Compliance\ComplianceService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VehicleResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'vehicles';

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & compliance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'registration';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehicle')->columns(3)->schema([
                TextInput::make('make')->required(), TextInput::make('model')->required(), TextInput::make('year')->numeric()->minValue(1990)->maxValue(now()->year + 1),
                TextInput::make('colour'), TextInput::make('registration')->required()->unique(ignoreRecord: true),
                Select::make('vehicle_class')->options(Vehicle::CLASSES)->required()->native(false),
                TextInput::make('seats')->numeric()->required()->minValue(1)->maxValue(12)->helperText('Passenger seats available to clients.'),
                Toggle::make('is_armoured')->inline(false),
                Select::make('status')->options(['active' => 'Active', 'maintenance' => 'In maintenance', 'retired' => 'Retired'])->required()->native(false)->default('active'),
            ]),
            Section::make('Ownership & features')->columns(2)->schema([
                Select::make('ownership')->options(['company' => 'Company fleet', 'operator' => 'Operator-owned'])->required()->native(false)->live(),
                Select::make('owner_operator_id')->label('Owner')->relationship('owner', 'call_sign')->getOptionLabelFromRecordUsing(fn ($record) => $record->label())
                    ->visible(fn ($get) => $get('ownership') === 'operator')->native(false)->searchable(),
                Select::make('features')->relationship('features', 'name', fn ($query) => $query->where('applies_to', 'vehicle'))->multiple()->preload()->native(false)->columnSpanFull(),
                FileUpload::make('photo_path')->image()->disk('public')->directory('vehicles')->imageEditor(),
                Textarea::make('notes')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['features', 'documents']))->columns([
            TextColumn::make('registration')->weight('bold')->searchable(),
            TextColumn::make('label')->state(fn ($record) => trim("{$record->colour} {$record->make} {$record->model}"))->label('Vehicle'),
            TextColumn::make('vehicle_class')->badge()->formatStateUsing(fn ($state) => Vehicle::CLASSES[$state] ?? $state),
            TextColumn::make('seats'),
            IconColumn::make('is_armoured')->boolean()->label('Armoured'),
            TextColumn::make('ownership')->badge()->color('gray'),
            TextColumn::make('roadworthy')->label('Documents')->badge()
                ->state(fn ($record) => app(ComplianceService::class)->vehicleRoadworthy($record) ? 'OK' : 'Missing / expired')
                ->color(fn ($state) => $state === 'OK' ? 'success' : 'danger'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'warning'),
        ])->filters([SelectFilter::make('status')->options(['active' => 'Active', 'maintenance' => 'Maintenance', 'retired' => 'Retired'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
