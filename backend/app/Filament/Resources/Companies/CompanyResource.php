<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CompanyResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'companies';

    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Company accounts';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->native(false)->default('active'),
                TextInput::make('registration_number'), TextInput::make('vat_number')->label('VAT number'),
                TextInput::make('billing_email')->email(), TextInput::make('billing_phone')->tel(),
                Textarea::make('billing_address')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Payment terms')->columns(2)->schema([
                Toggle::make('eft_approved')->label('Approved for monthly EFT invoicing'),
                TextInput::make('payment_terms_days')->numeric()->suffix('days')->label('Invoice due after'),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold')->searchable(),
            TextColumn::make('users_count')->counts('users')->label('Bookers'),
            TextColumn::make('bookings_count')->counts('bookings')->label('Bookings'),
            IconColumn::make('eft_approved')->boolean()->label('EFT'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\UsersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
