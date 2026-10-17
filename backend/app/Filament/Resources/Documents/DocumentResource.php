<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Document;
use App\Models\OperatorProfile;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DocumentResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'documents';

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & compliance';

    protected static ?string $navigationLabel = 'Document expiry';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $n = Document::query()->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($w) => $w->whereNotNull('expires_on')->whereDate('expires_on', '<=', now()->addDays(30))))->count();

        return $n ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false; // documents are added on the operator / vehicle page
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['type', 'documentable']))
            ->defaultSort('expires_on')
            ->columns([
                TextColumn::make('holder')->label('Holder')->weight('bold')->state(fn (Document $record) => match (true) {
                    $record->documentable instanceof OperatorProfile => $record->documentable->label(),
                    $record->documentable instanceof Vehicle => $record->documentable->label(),
                    default => '—',
                }),
                TextColumn::make('type.name')->label('Document'),
                TextColumn::make('expires_on')->date('j M Y')->sortable()
                    ->color(fn (Document $record) => ! $record->expires_on ? null : ($record->expires_on->isPast() ? 'danger' : ($record->expires_on->lte(now()->addDays(30)) ? 'warning' : null)))
                    ->description(fn (Document $record) => $record->expires_on ? ($record->expires_on->isPast() ? 'Expired '.$record->expires_on->diffForHumans() : 'in '.$record->expires_on->diffForHumans(null, true)) : null),
                TextColumn::make('status')->badge()->color(fn ($state) => ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$state] ?? 'gray'),
            ])
            ->filters([
                Filter::make('attention')->label('Needs attention')->default()->query(fn ($query) => $query->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($w) => $w->whereNotNull('expires_on')->whereDate('expires_on', '<=', now()->addDays(60))))),
                SelectFilter::make('document_type_id')->relationship('type', 'name')->label('Type'),
            ])
            ->recordActions([
                Action::make('approve')->icon('heroicon-o-check')->color('success')->visible(fn (Document $record) => $record->status === 'pending' && auth()->user()->can('documents.update'))
                    ->action(fn (Document $record) => $record->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])),
                Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(fn (Document $record) => $record->documentable instanceof OperatorProfile
                    ? \App\Filament\Resources\Operators\OperatorResource::getUrl('edit', ['record' => $record->documentable_id])
                    : \App\Filament\Resources\Vehicles\VehicleResource::getUrl('edit', ['record' => $record->documentable_id])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocuments::route('/')];
    }
}
