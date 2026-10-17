<?php

namespace App\Filament\Resources\Operators;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Feature;
use App\Models\OperatorProfile;
use App\Models\Vehicle;
use App\Services\Compliance\ComplianceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class OperatorResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'operators';

    protected static ?string $model = OperatorProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Drivers & bodyguards';

    protected static ?string $modelLabel = 'operator';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(2)->schema([
                Section::make('Person')->columns(2)->schema([
                    TextInput::make('user_name')->label('Full name')->required()->maxLength(100),
                    TextInput::make('user_phone')->label('Mobile number')->required()->tel()->helperText('They log into the Crew app with this number.'),
                    TextInput::make('user_email')->label('Email')->email(),
                    FileUpload::make('user_avatar')->label('Photo (shown to clients)')->image()->avatar()->disk('public')->directory('avatars')->imageEditor(),
                ]),
                Section::make('Role')->columns(3)->schema([
                    TextInput::make('call_sign')->maxLength(24)->unique(ignoreRecord: true)->placeholder('BW-07'),
                    Select::make('employment_type')->options(['employee' => 'Employee', 'contractor' => 'Contractor'])->required()->native(false)->live(),
                    Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'])->required()->native(false)->default('active'),
                    CheckboxList::make('roles')->options(['driver' => 'Chauffeur / driver', 'guard' => 'Protection officer'])->required()->columns(2)
                        ->afterStateHydrated(fn ($component, $state) => $component->state(is_string($state) ? array_filter(explode(',', $state)) : ($state ?? [])))
                        ->dehydrateStateUsing(fn ($state) => implode(',', (array) $state)),
                    Select::make('gender')->options(['female' => 'Female', 'male' => 'Male', 'other' => 'Other / prefer not to say'])->native(false),
                    DatePicker::make('hired_on')->native(false),
                    TextInput::make('psira_number')->label('PSIRA number'),
                    Select::make('psira_grade')->label('PSIRA grade')->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'])->native(false),
                    Select::make('region_id')->relationship('region', 'name')->native(false)->default(fn () => \App\Models\Region::query()->value('id')),
                ]),
                Section::make('Capabilities')->schema([
                    Select::make('features')->relationship('features', 'name', fn ($query) => $query->where('applies_to', 'operator')->orderBy('sort'))
                        ->multiple()->preload()->native(false)->helperText('Features linked to documents (e.g. Armed) only count while those documents are valid.'),
                    Select::make('current_vehicle_id')->label('Current vehicle')->native(false)->searchable()
                        ->options(fn () => Vehicle::query()->where('status', 'active')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()])),
                ]),
            ]),
            Group::make()->schema([
                Section::make('Compliance')->schema([
                    Placeholder::make('compliance')->hiddenLabel()->content(function ($record) {
                        if (! $record) {
                            return 'Save the operator, then add their documents below.';
                        }
                        $issues = app(ComplianceService::class)->issues($record);

                        return new HtmlString($issues
                            ? '<ul style="color:#fda4af;font-size:.85rem;list-style:disc;padding-left:1rem">'.collect($issues)->map(fn ($i) => '<li>'.e($i).'</li>')->implode('').'</ul>'
                            : '<span style="color:#6ee7b7">All required documents valid.</span>');
                    }),
                ]),
                Section::make('Pay (contractors)')->visible(fn ($get) => $get('employment_type') === 'contractor')->schema([
                    TextInput::make('pay_rate_hourly')->numeric()->prefix('R')->label('Per hour'),
                    TextInput::make('pay_rate_km')->numeric()->prefix('R')->label('Per km (drivers)'),
                    Textarea::make('bank_details')->rows(3)->helperText('Stored encrypted.'),
                ]),
                Section::make('Notes')->schema([Textarea::make('staff_notes')->hiddenLabel()->rows(4)]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'features', 'documents', 'currentVehicle']))
            ->defaultSort('call_sign')
            ->columns([
                TextColumn::make('call_sign')->label('Call sign')->sortable()->searchable()->weight('bold'),
                TextColumn::make('user.name')->label('Name')->searchable()->description(fn ($record) => $record->user?->phone),
                TextColumn::make('roles')->badge()->separator(',')->formatStateUsing(fn ($state) => $state === 'driver' ? 'Chauffeur' : 'Protection'),
                TextColumn::make('employment_type')->badge()->color('gray'),
                IconColumn::make('is_online')->label('Online')->boolean(),
                TextColumn::make('effective_features')->label('Active features')->badge()->color('primary')
                    ->state(fn ($record) => app(ComplianceService::class)->effectiveFeatures($record)->pluck('name')->all()),
                TextColumn::make('compliance')->badge()
                    ->state(fn ($record) => ($n = count(app(ComplianceService::class)->issues($record))) ? "{$n} issue".($n > 1 ? 's' : '') : 'OK')
                    ->color(fn ($state) => $state === 'OK' ? 'success' : 'danger'),
                TextColumn::make('currentVehicle.registration')->label('Vehicle'),
                TextColumn::make('rating_avg')->label('Rating')->numeric(1)->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended']),
                SelectFilter::make('employment_type')->options(['employee' => 'Employee', 'contractor' => 'Contractor']),
                TernaryFilter::make('is_online')->label('Online now'),
                SelectFilter::make('features')->relationship('features', 'name')->multiple()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('offline')->label('Take offline')->icon('heroicon-o-signal-slash')->color('gray')->visible(fn ($record) => $record->is_online)
                    ->requiresConfirmation()->action(function ($record) {
                        $record->update(['is_online' => false, 'online_since' => null]);
                        Notification::make()->title('Operator taken offline')->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\AssignmentsRelationManager::class,
            RelationManagers\RosterShiftsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperators::route('/'),
            'create' => Pages\CreateOperator::route('/create'),
            'edit' => Pages\EditOperator::route('/{record}/edit'),
        ];
    }

    /** Moves the user_* form fields onto the related User record. */
    public static function syncUser(OperatorProfile $profile, array $data): void
    {
        $user = $profile->user;
        $user->fill([
            'name' => $data['user_name'] ?? $user->name,
            'phone' => \App\Support\PhoneNumber::normalize($data['user_phone'] ?? $user->phone) ?? $user->phone,
            'email' => $data['user_email'] ?? $user->email,
        ]);
        if (array_key_exists('user_avatar', $data)) {
            $user->avatar_path = $data['user_avatar'];
        }
        $user->save();
    }
}
