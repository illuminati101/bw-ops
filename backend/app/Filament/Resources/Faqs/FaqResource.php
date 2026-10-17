<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\DraftColumns;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'content';

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'FAQs';

    protected static ?string $modelLabel = 'FAQ';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')->options(fn () => Faq::query()->distinct()->pluck('category', 'category')->all() + ['Booking' => 'Booking', 'Safety & privacy' => 'Safety & privacy', 'Payment' => 'Payment', 'Operators' => 'Operators'])
                ->required()->native(false)->createOptionForm([TextInput::make('category')->required()])->createOptionUsing(fn (array $data) => $data['category']),
            TextInput::make('question')->required()->maxLength(200)->columnSpanFull(),
            Textarea::make('answer')->required()->rows(5)->columnSpanFull(),
            TextInput::make('sort')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([
            TextColumn::make('question')->weight('bold')->searchable()->wrap(),
            TextColumn::make('category')->badge()->color('gray'),
            DraftColumns::status(),
        ])->filters([SelectFilter::make('category')->options(fn () => Faq::query()->distinct()->pluck('category', 'category')), DraftColumns::filter()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
