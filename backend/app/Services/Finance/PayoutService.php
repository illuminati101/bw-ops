<?php

namespace App\Services\Finance;

use App\Enums\AssignmentStatus;
use App\Models\BookingAssignment;
use App\Models\OperatorProfile;
use App\Models\Payout;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /** Group unpaid contractor work in the period into one payout per operator. @return int payouts created */
    public function generate(CarbonInterface $from, CarbonInterface $to): int
    {
        $created = 0;
        $rows = BookingAssignment::query()
            ->where('status', AssignmentStatus::Done->value)
            ->whereNull('payout_id')->whereNotNull('payout_amount')
            ->whereBetween('finished_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()->groupBy('operator_profile_id');

        foreach ($rows as $operatorId => $assignments) {
            DB::transaction(function () use ($operatorId, $assignments, $from, $to, &$created) {
                $payout = Payout::create([
                    'operator_profile_id' => $operatorId,
                    'period_start' => $from->toDateString(),
                    'period_end' => $to->toDateString(),
                    'jobs_count' => $assignments->count(),
                    'total' => round($assignments->sum('payout_amount'), 2),
                ]);
                BookingAssignment::query()->whereIn('id', $assignments->pluck('id'))->update(['payout_id' => $payout->id]);
                $created++;
            });
        }

        return $created;
    }

    public function earningsSummary(OperatorProfile $op): array
    {
        $base = $op->assignments()->where('status', AssignmentStatus::Done->value)->whereNotNull('payout_amount');

        return [
            'this_week' => round((clone $base)->where('finished_at', '>=', now()->startOfWeek())->sum('payout_amount'), 2),
            'this_month' => round((clone $base)->where('finished_at', '>=', now()->startOfMonth())->sum('payout_amount'), 2),
            'unpaid' => round((clone $base)->where(fn ($q) => $q->whereNull('payout_id')->orWhereHas('payout', fn ($p) => $p->where('status', 'pending')))->sum('payout_amount'), 2),
            'jobs_this_month' => (clone $base)->where('finished_at', '>=', now()->startOfMonth())->count(),
        ];
    }
}
