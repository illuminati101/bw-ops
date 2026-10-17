<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return BookingResource::canCreate() ? [CreateAction::make()->label('New booking (phone / walk-in)')] : [];
    }

    public function getTabs(): array
    {
        $v = fn (array $s) => array_map(fn ($x) => $x->value, $s);
        $needs = fn ($query) => $query->where(fn ($w) => $w->whereIn('status', [BookingStatus::PendingApproval->value, BookingStatus::QuoteRequested->value])
            ->orWhere(fn ($r) => $r->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Assigned->value])->whereNotNull('reconfirm_alerted_at')->whereNull('reconfirmed_at'))
            ->orWhere(fn ($r) => $r->where('status', BookingStatus::Confirmed->value)->where('starts_at', '<=', now()->addHours((int) settings('escalation.advance_unassigned_hours'))))
            ->orWhere('payment_status', 'failed'));

        return [
            'action' => Tab::make('Needs action')->icon('heroicon-o-exclamation-triangle')->modifyQueryUsing($needs)->badge(fn () => $needs(Booking::query())->count() ?: null)->badgeColor('warning'),
            'live' => Tab::make('Live now')->icon('heroicon-o-signal')->modifyQueryUsing(fn ($query) => $query->whereIn('status', $v(BookingStatus::active())))->badge(fn () => Booking::query()->live()->count() ?: null),
            'today' => Tab::make('Today')->modifyQueryUsing(fn ($query) => $query->whereDate('starts_at', today())->orderBy('starts_at')),
            'upcoming' => Tab::make('Upcoming')->modifyQueryUsing(fn ($query) => $query->where('starts_at', '>', now())->whereNotIn('status', $v(BookingStatus::closed()))->reorder('starts_at')),
            'past' => Tab::make('Past')->modifyQueryUsing(fn ($query) => $query->whereIn('status', $v(BookingStatus::closed()))),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'action';
    }
}
