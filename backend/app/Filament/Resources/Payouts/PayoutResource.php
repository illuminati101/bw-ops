<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Payout;
use App\Services\Finance\PayoutService;
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

class PayoutResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'finance';

    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Contractor payouts';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with('operator.user'))->columns([
            TextColumn::make('operator.call_sign')->label('Operator')->weight('bold')->description(fn ($record) => $record->operator?->user?->name),
            TextColumn::make('period')->state(fn ($record) => $record->period_start->format('j M').' – '.$record->period_end->format('j M Y')),
            TextColumn::make('jobs_count')->label('Jobs'),
            TextColumn::make('total')->money('ZAR'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
            TextColumn::make('paid_at')->dateTime('j M Y')->placeholder('—'),
            TextColumn::make('reference')->placeholder('—'),
        ])->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'paid' => 'Paid'])])
            ->headerActions([
                Action::make('generate')->label('Generate payouts')->icon('heroicon-o-sparkles')->visible(fn () => auth()->user()->can('finance.update'))
                    ->schema([DatePicker::make('from')->required()->default(now()->subWeek()->startOfWeek())->native(false), DatePicker::make('to')->required()->default(now()->subWeek()->endOfWeek())->native(false)])
                    ->action(function (array $data) {
                        $n = app(PayoutService::class)->generate(\Carbon\Carbon::parse($data['from']), \Carbon\Carbon::parse($data['to']));
                        Notification::make()->title("{$n} payout(s) created")->success()->send();
                    }),
                Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')->url(route('staff.export', 'payouts')),
            ])
            ->recordActions([
                Action::make('paid')->label('Mark paid')->icon('heroicon-o-banknotes')->color('success')->visible(fn ($record) => $record->status === 'pending' && auth()->user()->can('finance.update'))
                    ->schema([TextInput::make('reference')->label('EFT reference')->required()])
                    ->action(fn ($record, array $data) => $record->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => auth()->id(), 'reference' => $data['reference']])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayouts::route('/')];
    }
}
