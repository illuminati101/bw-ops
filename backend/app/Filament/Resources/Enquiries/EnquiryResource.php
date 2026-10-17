<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Enquiry;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EnquiryResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'enquiries';

    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Enquiry::query()->where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Placeholder::make('from')->content(fn ($record) => "{$record->name} · {$record->email} · {$record->phone}".($record->company ? " · {$record->company}" : '')),
            Placeholder::make('type')->content(fn ($record) => ucfirst($record->type).' · '.$record->created_at->format('D j M Y H:i')),
            Placeholder::make('message')->content(fn ($record) => $record->message)->columnSpanFull(),
            Select::make('status')->options(['new' => 'New', 'in_progress' => 'In progress', 'closed' => 'Closed'])->native(false),
            Textarea::make('staff_notes')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->since()->label('Received'),
            TextColumn::make('type')->badge()->color(fn ($state) => $state === 'corporate' ? 'primary' : 'gray'),
            TextColumn::make('name')->weight('bold')->description(fn ($record) => $record->company)->searchable(),
            TextColumn::make('email')->copyable(),
            TextColumn::make('message')->limit(70)->wrap(),
            TextColumn::make('status')->badge()->color(fn ($state) => ['new' => 'warning', 'closed' => 'success'][$state] ?? 'info'),
        ])->filters([SelectFilter::make('type')->options(['contact' => 'Contact', 'corporate' => 'Corporate']), SelectFilter::make('status')->options(['new' => 'New', 'in_progress' => 'In progress', 'closed' => 'Closed'])])
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEnquiries::route('/'), 'edit' => Pages\EditEnquiry::route('/{record}/edit')];
    }
}
