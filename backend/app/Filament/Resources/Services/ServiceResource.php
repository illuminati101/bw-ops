<?php

namespace App\Filament\Resources\Services;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\DraftColumns;
use App\Models\Feature;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ServiceResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'catalog';

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Services & pricing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Details')->icon('heroicon-o-document-text')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true)->helperText('Used in the web address: /services/your-slug'),
                        Select::make('category')->options(Service::CATEGORIES)->required()->native(false),
                        TextInput::make('tagline')->maxLength(80)->placeholder('Arrive composed.'),
                    ]),
                    Textarea::make('summary')->rows(2)->maxLength(250)->helperText('One or two sentences, shown on service cards.'),
                    Textarea::make('description')->rows(8)->helperText('Full description for the service page. Leave a blank line between paragraphs.'),
                    TagsInput::make('highlights')->placeholder('Add a highlight and press Enter')->helperText('Short bullet points shown with a tick.'),
                    TextInput::make('ideal_for')->maxLength(200),
                    Grid::make(2)->schema([
                        FileUpload::make('image_path')->label('Image')->image()->disk('public')->directory('services')->imageEditor()->maxSize(6144),
                        Select::make('icon')->native(false)->options([
                            'car' => 'Car', 'shield' => 'Shield', 'shield-check' => 'Shield (tick)', 'plane' => 'Plane',
                            'users' => 'Team', 'calendar' => 'Calendar', 'star' => 'Star', 'map-pin' => 'Map pin',
                        ]),
                    ]),
                ]),
                Tab::make('Booking rules')->icon('heroicon-o-adjustments-horizontal')->schema([
                    Section::make('What the service includes')->columns(4)->schema([
                        Toggle::make('includes_transport')->label('Vehicle & chauffeur'),
                        Toggle::make('includes_protection')->label('Protection officer(s)'),
                        TextInput::make('default_vehicles')->numeric()->minValue(0)->maxValue(10)->label('Vehicles (default)'),
                        TextInput::make('default_guards')->numeric()->minValue(0)->maxValue(20)->label('Officers (default)'),
                    ]),
                    Section::make('How it can be booked')->columns(3)->schema([
                        Toggle::make('allows_on_demand')->label('Hail on demand'),
                        Toggle::make('allows_advance')->label('Book in advance'),
                        Toggle::make('custom_quote')->label('Staff send a custom quote')->helperText('Client sees "Request a quote" instead of a price.'),
                        Select::make('structure')->native(false)->options(['single' => 'Single job', 'team' => 'Team booking', 'multi_day' => 'Multi-day / recurring'])
                            ->helperText('Team and multi-day services can be switched off globally in Settings.'),
                        Select::make('approval_rule')->native(false)->label('Staff approval')->options([
                            'never' => 'Not needed (trusted clients confirm instantly)',
                            'first_booking' => 'For a client\'s first booking',
                            'always' => 'Always',
                        ]),
                        TextInput::make('min_notice_minutes')->numeric()->label('Minimum notice (minutes)')->helperText('120 = 2 hours, 2880 = 48 hours'),
                        TextInput::make('min_duration_minutes')->numeric()->label('Minimum duration (minutes)'),
                    ]),
                    Section::make('Defaults and add-ons')->schema([
                        Select::make('default_feature_ids')->label('Always require these features')->multiple()->native(false)
                            ->options(fn () => Feature::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                            ->helperText('e.g. make "Armed" mandatory for this service.'),
                        CheckboxList::make('extras')->relationship('extras', 'name')->columns(3)->label('Add-ons clients can choose'),
                        TextInput::make('sort')->numeric()->label('Display order'),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn ($record) => $record->tagline),
                TextColumn::make('category')->badge()->formatStateUsing(fn ($state) => Service::CATEGORIES[$state] ?? $state),
                IconColumn::make('allows_on_demand')->label('On demand')->boolean(),
                IconColumn::make('custom_quote')->label('Custom quote')->boolean(),
                TextColumn::make('structure')->badge()->color('gray'),
                DraftColumns::status(),
                TextColumn::make('updated_at')->since()->label('Updated')->sortable(),
            ])
            ->filters([DraftColumns::filter()])
            ->recordActions([
                EditAction::make(),
                Action::make('rates')->label('Rates')->icon('heroicon-o-banknotes')->color('gray')
                    ->url(fn (Service $record) => ($card = $record->rateCards()->first()) ? \App\Filament\Resources\RateCards\RateCardResource::getUrl('edit', ['record' => $card]) : null),
                Action::make('preview')->icon('heroicon-o-eye')->color('gray')->url(fn (Service $record) => route('services.show', $record->slug).'?preview=1', true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
