<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\User;
use App\Services\Messaging\Notifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClientResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'clients';

    protected static ?string $model = User::class;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $modelLabel = 'client';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'client')->with('clientProfile');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getNavigationBadge(): ?string
    {
        $n = \App\Models\ClientProfile::query()->where('verification_status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for ID verification';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->unique(ignoreRecord: true),
                TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true)->dehydrateStateUsing(fn ($state) => \App\Support\PhoneNumber::normalize($state) ?? $state),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended', 'blocked' => 'Blocked'])->required()->native(false),
                Toggle::make('marketing_opt_in')->label('Accepts marketing'),
            ]),
            Section::make('Account settings')->relationship('clientProfile')->columns(2)->schema([
                TextInput::make('code_name')->label('Code name (shown to operators)')->maxLength(40),
                Select::make('trust_level')->options(['new' => 'New', 'trusted' => 'Trusted (simple bookings confirm instantly)'])->native(false),
                Toggle::make('requires_approval')->label('Every booking needs approval'),
                Toggle::make('eft_approved')->label('May pay by EFT invoice'),
                Textarea::make('staff_notes')->rows(3)->columnSpanFull()->helperText('Never shown to the client or operators.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Client')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('name'), TextEntry::make('phone')->copyable(), TextEntry::make('email')->copyable(),
                TextEntry::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextEntry::make('clientProfile.trust_level')->label('Trust')->badge(),
                TextEntry::make('strikes_count')->label('Active strikes')->state(fn ($record) => $record->activeStrikesCount()),
                TextEntry::make('companies.name')->label('Companies')->badge()->placeholder('—'),
                TextEntry::make('created_at')->label('Joined')->since(),
                TextEntry::make('clientProfile.code_name')->label('Code name')->placeholder('—'),
                TextEntry::make('clientProfile.staff_notes')->label('Staff notes')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Identity verification')->schema([
                TextEntry::make('clientProfile.verification_status')->label('Status')->badge()
                    ->color(fn ($state) => ['verified' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$state] ?? 'gray'),
                Grid::make(2)->schema([
                    ImageEntry::make('id_doc')->label('ID / passport')->state(fn ($record) => $record->clientProfile?->id_document_path ? route('staff.files.client', [$record->clientProfile, 'id']) : null)->height(120)->openUrlInNewTab(),
                    ImageEntry::make('selfie')->label('Selfie')->state(fn ($record) => $record->clientProfile?->selfie_path ? route('staff.files.client', [$record->clientProfile, 'selfie']) : null)->height(120),
                ]),
                TextEntry::make('clientProfile.verification_notes')->label('Notes')->placeholder('—'),
            ]),
        ]);
    }

    public static function viewHeaderActions($page): array
    {
        $record = $page->getRecord();
        $can = fn ($p) => auth()->user()->can($p);

        return [
            Action::make('verify')->label('Approve ID')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => $record->clientProfile?->verification_status === 'pending' && $can('clients.verify'))
                ->requiresConfirmation()->modalDescription('Confirm the photo matches the ID document and the name on the account.')
                ->action(function () use ($record) {
                    $record->clientProfile->update(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => auth()->id()]);
                    app(Notifier::class)->verificationResult($record, true);
                    Notification::make()->title('Client verified')->success()->send();
                }),
            Action::make('reject')->label('Reject ID')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => in_array($record->clientProfile?->verification_status, ['pending', 'verified'], true) && $can('clients.verify'))
                ->schema([Textarea::make('note')->label('Reason (sent to the client)')->required()])
                ->action(function (array $data) use ($record) {
                    $record->clientProfile->update(['verification_status' => 'rejected', 'verification_notes' => $data['note'], 'verified_by' => auth()->id()]);
                    app(Notifier::class)->verificationResult($record, false, $data['note']);
                    Notification::make()->title('Verification rejected')->success()->send();
                }),
            Action::make('suspend')->label(fn () => $record->status === 'active' ? 'Suspend' : 'Reactivate')->icon('heroicon-o-no-symbol')->color('gray')
                ->visible(fn () => $can('clients.update'))->requiresConfirmation()
                ->action(function () use ($record) {
                    $record->update(['status' => $record->status === 'active' ? 'suspended' : 'active']);
                    if ($record->status !== 'active') {
                        $record->tokens()->delete();
                    }
                }),
            EditAction::make(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->searchable()->weight('bold')->description(fn ($record) => $record->clientProfile?->code_name),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('email')->searchable()->toggleable(),
            TextColumn::make('clientProfile.verification_status')->label('ID')->badge()
                ->color(fn ($state) => ['verified' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$state] ?? 'gray'),
            TextColumn::make('clientProfile.trust_level')->label('Trust')->badge()->color(fn ($state) => $state === 'trusted' ? 'primary' : 'gray'),
            TextColumn::make('bookings_count')->counts('bookings')->label('Bookings')->sortable(),
            TextColumn::make('strikes_count')->label('Strikes')->state(fn ($record) => $record->activeStrikesCount() ?: null)->badge()->color('danger'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            TextColumn::make('created_at')->since()->label('Joined')->sortable(),
        ])->filters([
            SelectFilter::make('verification')->label('ID status')->options(['pending' => 'Waiting for review', 'verified' => 'Verified', 'unverified' => 'Not submitted', 'rejected' => 'Rejected'])
                ->query(fn ($query, array $data) => $data['value'] ? $query->whereHas('clientProfile', fn ($q) => $q->where('verification_status', $data['value'])) : $query),
            SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended', 'blocked' => 'Blocked']),
        ])->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\BookingsRelationManager::class, RelationManagers\StrikesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
