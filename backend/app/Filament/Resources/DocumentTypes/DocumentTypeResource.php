<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\DocumentType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class DocumentTypeResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'documents';

    protected static ?string $model = DocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & compliance';

    protected static ?string $navigationLabel = 'Document types';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)->afterStateUpdated(fn ($set, $state, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Select::make('applies_to')->options(['operator' => 'Operators', 'vehicle' => 'Vehicles'])->required()->native(false),
            TextInput::make('reminder_days')->numeric()->suffix('days')->label('Remind before expiry'),
            Toggle::make('requires_expiry')->label('Has an expiry date'),
            Toggle::make('required_for_all_jobs')->label('Required for any job')->helperText('Operators (or vehicles) without a valid copy cannot be dispatched.'),
            Toggle::make('required_for_driving')->label('Required to drive')->visible(fn ($get) => $get('applies_to') === 'operator'),
            Select::make('features')->relationship('features', 'name')->multiple()->preload()->native(false)->label('Unlocks features')->helperText('e.g. Firearm competency unlocks "Armed".'),
            Textarea::make('description')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold'),
            TextColumn::make('applies_to')->badge()->color('gray'),
            IconColumn::make('required_for_all_jobs')->boolean()->label('Any job'),
            IconColumn::make('required_for_driving')->boolean()->label('Driving'),
            TextColumn::make('features.name')->badge()->color('primary')->label('Unlocks'),
            TextColumn::make('reminder_days')->suffix(' days')->label('Reminder'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTypes::route('/'),
            'create' => Pages\CreateDocumentType::route('/create'),
            'edit' => Pages\EditDocumentType::route('/{record}/edit'),
        ];
    }
}
