<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\DraftColumns;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'content';

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    private static function image(string $name = 'image'): FileUpload
    {
        return FileUpload::make($name)->image()->disk('public')->directory('pages')->imageEditor()->maxSize(8192)
            ->helperText('Wide landscape image, at least 2000px. Dark images work best.');
    }

    private static function cta(): array
    {
        return [
            Grid::make(4)->schema([
                TextInput::make('primary_label')->label('Button text'),
                TextInput::make('primary_url')->label('Button link')->placeholder('/book'),
                TextInput::make('secondary_label')->label('Second button text'),
                TextInput::make('secondary_url')->label('Second button link'),
            ]),
        ];
    }

    public static function blocks(): array
    {
        $icons = ['badge-check' => 'Badge', 'steering-wheel' => 'Steering wheel', 'clock' => 'Clock', 'eye-off' => 'Privacy', 'lock' => 'Lock', 'target' => 'Target',
            'scale' => 'Scales', 'sparkles' => 'Sparkle', 'users' => 'People', 'file-text' => 'Document', 'user-check' => 'Person tick', 'bar-chart' => 'Chart',
            'shield' => 'Shield', 'car' => 'Car', 'phone' => 'Phone', 'map-pin' => 'Map pin', 'heart-pulse' => 'Medical'];

        return [
            Block::make('hero')->label('Hero banner')->icon('heroicon-o-photo')->schema([
                TextInput::make('eyebrow')->label('Small heading'),
                TextInput::make('heading')->required(),
                Textarea::make('subheading')->rows(2),
                ...self::cta(),
                self::image(),
                Toggle::make('compact')->label('Shorter banner (inner pages)'),
            ]),
            Block::make('pillars')->label('Feature grid')->icon('heroicon-o-squares-2x2')->schema([
                TextInput::make('eyebrow'), TextInput::make('heading'),
                Repeater::make('items')->schema([
                    Select::make('icon')->options($icons)->native(false),
                    TextInput::make('title')->required(),
                    Textarea::make('body')->rows(2),
                ])->columns(3)->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
            ]),
            Block::make('services')->label('Services list')->icon('heroicon-o-shield-check')->schema([
                TextInput::make('eyebrow'), TextInput::make('heading'), Textarea::make('intro')->rows(2),
                Toggle::make('detailed')->label('Show full details (services page)'),
            ]),
            Block::make('steps')->label('Numbered steps')->icon('heroicon-o-list-bullet')->schema([
                TextInput::make('eyebrow'), TextInput::make('heading'),
                Repeater::make('items')->schema([TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(2)->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
            ]),
            Block::make('split')->label('Image + text')->icon('heroicon-o-view-columns')->schema([
                TextInput::make('eyebrow'), TextInput::make('heading'), Textarea::make('body')->rows(5),
                TagsInput::make('bullets'),
                Select::make('visual')->options(['image' => 'Image', 'phone' => 'App illustration (arrival PIN)'])->default('image')->native(false),
                self::image(),
                Grid::make(3)->schema([
                    TextInput::make('cta_label')->label('Button text'), TextInput::make('cta_url')->label('Button link'),
                    Toggle::make('reverse')->label('Image on the left'),
                ]),
            ]),
            Block::make('text')->label('Text section')->icon('heroicon-o-bars-3-bottom-left')->schema([
                TextInput::make('eyebrow'), TextInput::make('heading'), Textarea::make('body')->rows(8),
            ]),
            Block::make('cta')->label('Call to action band')->icon('heroicon-o-megaphone')->schema([
                TextInput::make('heading')->required(), Textarea::make('body')->rows(2), ...self::cta(),
            ]),
            Block::make('faq')->label('FAQ list')->icon('heroicon-o-question-mark-circle')->schema([
                TextInput::make('heading'),
                Select::make('category')->options(fn () => \App\Models\Faq::query()->distinct()->pluck('category', 'category'))->placeholder('All categories'),
            ]),
            Block::make('form')->label('Form')->icon('heroicon-o-pencil-square')->schema([
                Select::make('form')->options(['contact' => 'Contact', 'corporate' => 'Corporate enquiry', 'careers' => 'Job application'])->required(),
                TextInput::make('heading'), Textarea::make('intro')->rows(2),
            ]),
            Block::make('contact')->label('Contact details + form')->icon('heroicon-o-phone')->schema([TextInput::make('heading')]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('title')->required()->maxLength(120),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->disabled(fn ($record) => $record && in_array($record->slug, ['home', 'about', 'services', 'faq', 'careers', 'corporate', 'contact', 'legal/terms', 'legal/privacy', 'legal/cancellation'], true))
                    ->helperText('System pages keep their address.'),
                Select::make('kind')->options(['page' => 'Page (blocks)', 'legal' => 'Legal document (text)'])->required()->native(false)->live()->disabled(fn ($record) => (bool) $record),
                TextInput::make('meta_description')->label('Search engine description')->maxLength(160)->columnSpanFull(),
            ]),
            Builder::make('blocks')->blocks(self::blocks())->collapsible()->collapsed()->blockNumbers(false)->reorderableWithButtons()
                ->visible(fn ($get) => $get('kind') !== 'legal')->columnSpanFull()->cloneable(),
            Section::make('Document text')->visible(fn ($get) => $get('kind') === 'legal')->schema([
                MarkdownEditor::make('body')->hiddenLabel()->toolbarButtons(['heading', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'table', 'undo', 'redo'])
                    ->helperText('Values in {{double braces}} are filled in from Settings (company details, cancellation tiers). Bold text marks clauses that limit liability (CPA section 49).'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id')->columns([
            TextColumn::make('title')->weight('bold')->searchable()->description(fn ($record) => '/'.($record->slug === 'home' ? '' : $record->slug)),
            TextColumn::make('kind')->badge()->color('gray'),
            DraftColumns::status(),
            TextColumn::make('updated_at')->since()->label('Updated'),
        ])->filters([DraftColumns::filter()])->recordActions([
            EditAction::make(),
            Action::make('view')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(fn ($record) => url($record->slug === 'home' ? '/' : $record->slug), true),
            Action::make('preview')->label('Preview draft')->icon('heroicon-o-eye')->color('warning')->visible(fn ($record) => $record->hasDraft())
                ->url(fn ($record) => \Illuminate\Support\Facades\URL::temporarySignedRoute('preview.page', now()->addHour(), ['page' => $record->id]), true),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
