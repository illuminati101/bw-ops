@php($vatReg = (bool) settings('company.vat_registered'))
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $vatReg ? 'Tax invoice' : 'Invoice' }} {{ $invoice->number }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 13px/1.5 "Hanken Grotesk", "Segoe UI", Arial, sans-serif; color: #1a1917; background: #f4f2ee; }
        .sheet { max-width: 820px; margin: 24px auto; background: #fff; padding: 48px; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #1a1917; padding-bottom: 20px; }
        h1 { font: 500 34px/1 "Cormorant Garamond", Georgia, serif; margin: 0 0 6px; }
        .muted { color: #6b665d; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: 600; border-bottom: 1px solid #1a1917; padding: 8px 6px; }
        td { border-bottom: 1px solid #e3dfd6; padding: 8px 6px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 300px; margin-top: 16px; }
        .totals td { border: 0; padding: 4px 6px; }
        .totals .grand td { border-top: 2px solid #1a1917; font-weight: 700; font-size: 15px; padding-top: 8px; }
        .bank { white-space: pre-line; background: #f7f5f0; padding: 14px 16px; margin-top: 28px; }
        .actions { max-width: 820px; margin: 16px auto 0; text-align: right; }
        .actions button { font: inherit; padding: 8px 16px; border: 1px solid #1a1917; background: #1a1917; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; max-width: none; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
    <div class="sheet">
        <header>
            <div>
                <h1>{{ $vatReg ? 'Tax invoice' : 'Invoice' }}</h1>
                <div><strong>{{ $invoice->number }}</strong></div>
                <div class="muted">Issued {{ ($invoice->sent_at ?? $invoice->created_at)->format('j F Y') }}@if ($invoice->due_on), due {{ $invoice->due_on->format('j F Y') }}@endif</div>
            </div>
            <div style="text-align:right">
                <div><strong>{{ settings('company.legal_name') }}</strong></div>
                <div class="muted">Trading as {{ settings('company.trading_name') }}</div>
                <div class="muted" style="white-space:pre-line">{{ settings('company.physical_address') }}</div>
                <div class="muted">Reg. {{ settings('company.registration_number') }}</div>
                @if ($vatReg)<div class="muted">VAT {{ settings('company.vat_number') }}</div>@endif
                <div class="muted">PSIRA {{ settings('company.psira_number') }}</div>
            </div>
        </header>

        <div class="grid">
            <div>
                <div class="muted">Bill to</div>
                <strong>{{ $invoice->billTo() }}</strong>
                @if ($invoice->company)
                    @if ($invoice->company->registration_number ?? null)<div class="muted">Reg. {{ $invoice->company->registration_number }}</div>@endif
                    @if ($invoice->company->vat_number ?? null)<div class="muted">VAT {{ $invoice->company->vat_number }}</div>@endif
                    @if ($invoice->company->billing_address ?? null)<div class="muted" style="white-space:pre-line">{{ $invoice->company->billing_address }}</div>@endif
                    @if ($invoice->company->billing_email ?? null)<div class="muted">{{ $invoice->company->billing_email }}</div>@endif
                @elseif ($invoice->user)
                    <div class="muted">{{ $invoice->user->email }}</div>
                @endif
            </div>
            <div style="text-align:right">
                <div class="muted">Period</div>
                <strong>{{ $invoice->period_start?->format('j M Y') }} to {{ $invoice->period_end?->format('j M Y') }}</strong>
                <div class="muted" style="margin-top:6px">Status: {{ ucfirst($invoice->status) }}@if ($invoice->paid_at), paid {{ $invoice->paid_at->format('j M Y') }}@endif</div>
            </div>
        </div>

        <table>
            <thead><tr><th>Date</th><th>Reference</th><th>Service</th><th>Booked for</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @forelse ($invoice->bookings->sortBy('starts_at') as $b)
                    <tr>
                        <td>{{ $b->starts_at->format('j M Y') }}</td>
                        <td>{{ $b->reference }}</td>
                        <td>{{ $b->service->name }}<div class="muted">{{ \Illuminate\Support\Str::limit($b->pickup_address, 60) }}</div></td>
                        <td>{{ $b->principal_name ?? $b->client?->name }}</td>
                        <td class="num">{{ rands($b->final_total ?? $b->quoted_total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No bookings on this invoice.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            @if ($vatReg)
                <tr><td>Subtotal (excl. VAT)</td><td class="num">{{ rands($invoice->subtotal) }}</td></tr>
                <tr><td>VAT</td><td class="num">{{ rands($invoice->vat) }}</td></tr>
            @endif
            <tr class="grand"><td>Total due</td><td class="num">{{ rands($invoice->total) }}</td></tr>
        </table>

        <div class="bank"><strong>Pay by EFT</strong>
{{ settings('company.bank_details') }}
Reference: {{ $invoice->number }}</div>
        @if ($invoice->notes)<p class="muted" style="margin-top:20px">{{ $invoice->notes }}</p>@endif
        <p class="muted" style="margin-top:28px">Questions about this invoice? {{ settings('company.email') }}, {{ settings('company.phone') }}.</p>
    </div>
</body>
</html>
