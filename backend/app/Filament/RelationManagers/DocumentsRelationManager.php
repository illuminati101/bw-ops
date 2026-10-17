<?php

namespace App\Filament\RelationManagers;

use App\Models\Document;
use App\Models\DocumentType;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Compliance documents';

    public function form(Schema $schema): Schema
    {
        $appliesTo = $this->getOwnerRecord() instanceof \App\Models\Vehicle ? 'vehicle' : 'operator';

        return $schema->columns(2)->components([
            Select::make('document_type_id')->label('Document')->required()->native(false)
                ->options(DocumentType::query()->where('applies_to', $appliesTo)->orderBy('name')->pluck('name', 'id')),
            TextInput::make('number')->label('Certificate / document number'),
            DatePicker::make('issued_on')->native(false),
            DatePicker::make('expires_on')->native(false)->helperText('Staff are alerted before this date; the related features switch off if it lapses.'),
            FileUpload::make('file_path')->label('Scan / photo')->disk('local')->directory('documents')->acceptedFileTypes(['image/*', 'application/pdf'])->maxSize(10240)->downloadable()->columnSpanFull(),
            Select::make('status')->options(['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('approved')->required()->native(false),
            Textarea::make('notes')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('number')
            ->defaultSort('expires_on')
            ->columns([
                TextColumn::make('type.name')->label('Document')->weight('bold'),
                TextColumn::make('number'),
                TextColumn::make('expires_on')->date('j M Y')->sortable()
                    ->color(fn (Document $record) => ! $record->expires_on ? null : ($record->expires_on->isPast() ? 'danger' : ($record->expires_on->lte(now()->addDays(30)) ? 'warning' : null)))
                    ->description(fn (Document $record) => $record->expires_on ? ($record->expires_on->isPast() ? 'Expired' : $record->expires_on->diffForHumans()) : 'No expiry'),
                TextColumn::make('status')->badge()->color(fn ($state) => ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$state] ?? 'gray'),
            ])
            ->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['reviewed_by' => auth()->id(), 'reviewed_at' => now()])])
            ->recordActions([
                Action::make('approve')->icon('heroicon-o-check')->color('success')->visible(fn (Document $record) => $record->status === 'pending')
                    ->action(fn (Document $record) => $record->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])),
                Action::make('download')->icon('heroicon-o-arrow-down-tray')->color('gray')->visible(fn (Document $record) => (bool) $record->file_path)
                    ->action(fn (Document $record) => \Storage::disk('local')->download($record->file_path)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
