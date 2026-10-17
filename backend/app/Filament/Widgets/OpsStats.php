<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientProfile;
use App\Models\OperatorProfile;
use App\Models\SosAlert;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $sos = SosAlert::query()->whereIn('status', ['open', 'acknowledged'])->count();
        $pending = Booking::query()->whereIn('status', [BookingStatus::PendingApproval->value, BookingStatus::QuoteRequested->value])->count();

        return array_values(array_filter([
            $sos ? Stat::make('Active SOS', $sos)->color('danger')->description('Open the control room')->url(\App\Filament\Pages\ControlRoom::getUrl())->extraAttributes(['class' => 'bw-sos-flash']) : null,
            Stat::make('Live jobs', Booking::query()->live()->count())->description('Searching, assigned or in progress')->icon('heroicon-o-signal'),
            Stat::make('Operators online', OperatorProfile::query()->where('is_online', true)->count().' / '.OperatorProfile::query()->active()->count())->icon('heroicon-o-identification'),
            Stat::make('Needs approval', $pending)->color($pending ? 'warning' : 'gray')->description('Bookings and quote requests')->url(\App\Filament\Resources\Bookings\BookingResource::getUrl()),
            Stat::make('Today', Booking::query()->whereDate('starts_at', today())->whereNotIn('status', ['cancelled', 'declined', 'expired'])->count())->description('bookings scheduled'),
            Stat::make('Revenue this month', rands(Booking::query()->where('status', 'completed')->where('completed_at', '>=', now()->startOfMonth())->sum('final_total'), false))->icon('heroicon-o-banknotes'),
            Stat::make('ID checks waiting', ClientProfile::query()->where('verification_status', 'pending')->count())->url(\App\Filament\Resources\Clients\ClientResource::getUrl()),
        ]));
    }
}
