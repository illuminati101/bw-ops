<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'finance';

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'EFT invoices';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('number')->weight('bold')->searchable(),
            TextColumn::make('bill_to')->state(fn ($record) => $record->billTo())->label('Bill to'),
            TextColumn::make('period')->state(fn ($record) => $record->period_start?->format('j M').' – '.$record->period_end?->format('j M Y')),
            TextColumn::make('bookings_count')->counts('bookings')->label('Bookings'),
            TextColumn::make('total')->money('ZAR'),
            TextColumn::make('due_on')->date('j M Y')->label('Due'),
            TextColumn::make('status')->badge()->color(fn ($state) => ['paid' => 'success', 'overdue' => 'danger', 'sent' => 'info'][$state] ?? 'gray'),
        ])->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'])])
            ->headerActions([
                Action::make('generate')->label('Generate invoices')->icon('heroicon-o-sparkles')->visible(fn () => auth()->user()->can('finance.update'))
                    ->schema([DatePicker::make('from')->required()->default(now()->subMonth()->startOfMonth())->native(false), DatePicker::make('to')->required()->default(now()->subMonth()->endOfMonth())->native(false)])
                    ->action(function (array $data) {
                        $n = app(InvoiceService::class)->generate(\Carbon\Carbon::parse($data['from']), \Carbon\Carbon::parse($data['to']));
                        Notification::make()->title("{$n} invoice(s) created")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('print')->label('View / print')->icon('heroicon-o-printer')->color('gray')->url(fn ($record) => route('staff.invoice', $record), true),
                Action::make('sent')->label('Mark sent')->icon('heroicon-o-paper-airplane')->visible(fn ($record) => $record->status === 'draft' && auth()->user()->can('finance.update'))
                    ->action(fn ($record) => $record->update(['status' => 'sent', 'sent_at' => now()])),
                Action::make('paid')->label('Mark paid')->icon('heroicon-o-banknotes')->color('success')->visible(fn ($record) => in_array($record->status, ['draft', 'sent', 'overdue'], true) && auth()->user()->can('finance.update'))
                    ->schema([TextInput::make('reference')->label('EFT reference')])
                    ->action(fn ($record, array $data) => app(InvoiceService::class)->markPaid($record, $data['reference'] ?? null)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/')];
    }
}
