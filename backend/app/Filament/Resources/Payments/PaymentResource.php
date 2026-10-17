<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionPrefix = 'finance';

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['booking', 'user']))->columns([
            TextColumn::make('created_at')->dateTime('D j M Y H:i')->label('Time')->sortable(),
            TextColumn::make('booking.reference')->label('Booking')->searchable()->url(fn ($record) => $record->booking ? \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $record->booking]) : null),
            TextColumn::make('user.name')->label('Client')->searchable(),
            TextColumn::make('type')->badge()->color('gray'),
            TextColumn::make('amount')->money('ZAR')->sortable(),
            TextColumn::make('gateway')->badge()->color(fn ($state) => $state === 'manual' ? 'warning' : 'gray')->formatStateUsing(fn ($state) => $state === 'manual' ? 'Record only' : $state),
            TextColumn::make('status')->badge()->color(fn ($state) => ['success' => 'success', 'failed' => 'danger'][$state] ?? 'warning'),
            TextColumn::make('description')->wrap()->toggleable(),
        ])->filters([
            SelectFilter::make('type')->options(['charge' => 'Charge', 'fee' => 'Fee', 'capture' => 'Capture', 'hold' => 'Hold', 'release' => 'Release', 'verify' => 'Card verification', 'refund' => 'Refund']),
            SelectFilter::make('status')->options(['success' => 'Success', 'failed' => 'Failed', 'pending' => 'Pending']),
            SelectFilter::make('gateway')->options(['paystack' => 'Paystack', 'manual' => 'Record only', 'card_machine' => 'Card machine', 'eft' => 'EFT']),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/')];
    }
}
