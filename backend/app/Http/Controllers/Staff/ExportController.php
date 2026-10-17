<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OperatorProfile;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Http\Request;

/** CSV exports for the accountant (opens directly in Excel). */
class ExportController extends Controller
{
    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user()->can('reports.view') || auth()->user()->can('finance.view'), 403);
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = ($request->date('to') ?? now())->endOfDay();

        [$headers, $rows] = match ($type) {
            'bookings' => [
                ['Reference', 'Created', 'Starts', 'Status', 'Mode', 'Service', 'Client', 'Company', 'Quote', 'Final', 'Fee', 'Payment method', 'Payment status', 'Operators'],
                Booking::query()->with(['service', 'client', 'company', 'activeAssignments.operator'])->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->get()
                    ->map(fn ($b) => [$b->reference, $b->created_at, $b->starts_at, $b->status->getLabel(), $b->mode, $b->service->name, $b->client->name, $b->company?->name, $b->quoted_total, $b->final_total, $b->cancellation_fee, $b->payment_method, $b->payment_status, $b->activeAssignments->map(fn ($a) => $a->operator->call_sign)->implode(' ')]),
            ],
            'payments' => [
                ['Date', 'Booking', 'Client', 'Type', 'Gateway', 'Amount', 'Status', 'Reference', 'Description'],
                Payment::query()->with(['booking', 'user'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get()
                    ->map(fn ($p) => [$p->created_at, $p->booking?->reference, $p->user?->name, $p->type, $p->gateway, $p->amount, $p->status, $p->gateway_reference, $p->description]),
            ],
            'payouts' => [
                ['Operator', 'Name', 'Period start', 'Period end', 'Jobs', 'Total', 'Status', 'Paid at', 'Reference'],
                Payout::query()->with('operator.user')->orderBy('period_start')->get()
                    ->map(fn ($p) => [$p->operator->call_sign, $p->operator->user->name, $p->period_start->toDateString(), $p->period_end->toDateString(), $p->jobs_count, $p->total, $p->status, $p->paid_at, $p->reference]),
            ],
            'clients' => [
                ['Name', 'Phone', 'Email', 'Joined', 'Verification', 'Trust', 'Bookings', 'Marketing opt-in'],
                User::query()->where('type', 'client')->with('clientProfile')->withCount('bookings')->get()
                    ->map(fn ($u) => [$u->name, $u->phone, $u->email, $u->created_at, $u->clientProfile?->verification_status, $u->clientProfile?->trust_level, $u->bookings_count, $u->marketing_opt_in ? 'yes' : 'no']),
            ],
            'operators' => [
                ['Call sign', 'Name', 'Phone', 'Type', 'Roles', 'Status', 'PSIRA', 'Grade', 'Rating'],
                OperatorProfile::query()->with('user')->get()->map(fn ($o) => [$o->call_sign, $o->user->name, $o->user->phone, $o->employment_type, $o->roles, $o->status, $o->psira_number, $o->psira_grade, $o->rating_avg]),
            ],
        };
        activity()->causedBy(auth()->user())->event('exported')->log("Exported {$type}");

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v, $row));
            }
            fclose($out);
        }, "bwops-{$type}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
