<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /** Create invoices for EFT bookings completed (or charged a fee) in the period. @return int invoices created */
    public function generate(CarbonInterface $from, CarbonInterface $to): int
    {
        $bookings = Booking::query()
            ->where('payment_method', 'eft')->whereNull('invoice_id')
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->where(fn ($q) => $q->where('final_total', '>', 0)->orWhere('cancellation_fee', '>', 0))
            ->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn ($b) => $b->company_id ? 'c'.$b->company_id : 'u'.$b->client_id);

        $count = 0;
        foreach ($bookings as $group) {
            DB::transaction(function () use ($group, $from, $to, &$count) {
                $first = $group->first();
                $total = round($group->sum(fn ($b) => $b->status->value === 'completed' ? $b->final_total : $b->cancellation_fee), 2);
                $vatRate = (float) settings('payments.vat_rate');
                $vat = settings('company.vat_registered') ? round($total * $vatRate / (100 + $vatRate), 2) : 0;
                $terms = $first->company?->payment_terms_days ?? 30;
                $invoice = Invoice::create([
                    'number' => $this->nextNumber(),
                    'company_id' => $first->company_id,
                    'user_id' => $first->company_id ? null : $first->client_id,
                    'period_start' => $from->toDateString(),
                    'period_end' => $to->toDateString(),
                    'subtotal' => round($total - $vat, 2),
                    'vat' => $vat,
                    'total' => $total,
                    'status' => 'draft',
                    'due_on' => now()->addDays($terms)->toDateString(),
                ]);
                Booking::query()->whereIn('id', $group->pluck('id'))->update(['invoice_id' => $invoice->id]);
                $count++;
            });
        }

        return $count;
    }

    public function markPaid(Invoice $invoice, ?string $reference): void
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now(), 'payment_reference' => $reference]);
        foreach ($invoice->bookings as $b) {
            $b->update(['payment_status' => 'paid']);
            Payment::query()->where('booking_id', $b->id)->where('gateway', 'eft')->where('status', 'pending')->update(['status' => 'success', 'gateway_reference' => $reference]);
        }
    }

    public function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $last = Invoice::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
