<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Payment;
use App\Models\Rating;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function preset(string $p): void
    {
        [$f, $t] = match ($p) {
            'today' => [today(), today()],
            'week' => [now()->startOfWeek(), now()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };
        $this->from = $f->toDateString();
        $this->to = $t->toDateString();
    }

    public function getViewData(): array
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $base = fn () => Booking::query()->whereBetween('bookings.starts_at', [$from, $to]);

        $completed = $base()->where('status', BookingStatus::Completed->value);
        $revenue = (float) (clone $completed)->sum('final_total') + (float) $base()->whereIn('status', ['cancelled', 'no_show'])->sum('cancellation_fee');
        $count = $base()->count();
        $byStatus = $base()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $byService = $base()->join('services', 'services.id', '=', 'bookings.service_id')
            ->select('services.name', DB::raw('count(*) as bookings'), DB::raw("sum(case when bookings.status = 'completed' then bookings.final_total else 0 end) as revenue"))
            ->groupBy('services.name')->orderByDesc('revenue')->get();

        $byOperator = BookingAssignment::query()->where('booking_assignments.status', 'done')
            ->whereBetween('finished_at', [$from, $to])
            ->join('operator_profiles', 'operator_profiles.id', '=', 'booking_assignments.operator_profile_id')
            ->join('users', 'users.id', '=', 'operator_profiles.user_id')
            ->select('operator_profiles.call_sign', 'users.name', 'operator_profiles.rating_avg', DB::raw('count(*) as jobs'),
                DB::raw('sum(coalesce(booking_assignments.payout_amount, 0)) as payouts'))
            ->groupBy('operator_profiles.call_sign', 'users.name', 'operator_profiles.rating_avg')->orderByDesc('jobs')->get();

        $topClients = $base()->where('bookings.status', 'completed')->join('users', 'users.id', '=', 'bookings.client_id')
            ->select('users.name', DB::raw('count(*) as bookings'), DB::raw('sum(bookings.final_total) as spend'))
            ->groupBy('users.name')->orderByDesc('spend')->limit(10)->get();

        return [
            'kpis' => [
                ['Revenue', rands($revenue, false), 'Completed jobs + fees'],
                ['Bookings', number_format($count), ($byStatus['completed'] ?? 0).' completed'],
                ['Average job', rands((clone $completed)->avg('final_total') ?? 0, false), 'Completed jobs'],
                ['No-shows', (string) ($byStatus['no_show'] ?? 0), ($byStatus['cancelled'] ?? 0).' cancelled'],
                ['On-demand share', $count ? round($base()->where('mode', 'on_demand')->count() / $count * 100).'%' : '—', 'of all bookings'],
                ['Client rating', number_format((float) Rating::query()->where('direction', 'client_to_operator')->whereBetween('created_at', [$from, $to])->avg('score'), 1).' / 5', Rating::query()->whereBetween('created_at', [$from, $to])->count().' ratings'],
                ['Payouts', rands($byOperator->sum('payouts'), false), 'Contractor earnings'],
                ['Failed payments', (string) Payment::query()->where('status', 'failed')->whereBetween('created_at', [$from, $to])->count(), 'need follow-up'],
            ],
            'byStatus' => $byStatus,
            'byService' => $byService,
            'byOperator' => $byOperator,
            'topClients' => $topClients,
            'exportQuery' => http_build_query(['from' => $this->from, 'to' => $this->to]),
        ];
    }
}
