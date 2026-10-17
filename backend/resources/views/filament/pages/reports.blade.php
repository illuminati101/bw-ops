<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">From <input type="date" wire:model.live="from" class="fi-input block rounded-lg border-white/10 bg-white/5 text-sm"></label>
        <label class="text-sm">To <input type="date" wire:model.live="to" class="fi-input block rounded-lg border-white/10 bg-white/5 text-sm"></label>
        @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'] as $k => $l)
            <x-filament::button size="sm" color="gray" wire:click="preset('{{ $k }}')">{{ $l }}</x-filament::button>
        @endforeach
        <div class="ml-auto flex flex-wrap gap-2">
            @foreach (['bookings' => 'Bookings', 'payments' => 'Payments', 'payouts' => 'Payouts', 'clients' => 'Clients', 'operators' => 'Operators'] as $k => $l)
                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" tag="a" href="{{ route('staff.export', $k) }}?{{ $exportQuery }}">{{ $l }} CSV</x-filament::button>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as [$label, $value, $hint])
            <div class="rounded-xl border border-white/10 bg-white/[.03] p-4">
                <div class="text-xs uppercase tracking-widest text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold" style="color:#E7D3A8">{{ $value }}</div>
                <div class="text-xs text-gray-500">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-filament::section heading="By service">
            <table class="w-full text-sm"><thead class="text-left text-gray-400"><tr><th>Service</th><th class="text-right">Bookings</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>@forelse ($byService as $r)<tr class="border-t border-white/5"><td class="py-1.5">{{ $r->name }}</td><td class="text-right">{{ $r->bookings }}</td><td class="text-right">{{ rands($r->revenue, false) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-gray-500">No bookings in this period.</td></tr>@endforelse</tbody></table>
        </x-filament::section>
        <x-filament::section heading="By operator">
            <table class="w-full text-sm"><thead class="text-left text-gray-400"><tr><th>Operator</th><th class="text-right">Jobs</th><th class="text-right">Rating</th><th class="text-right">Payouts</th></tr></thead>
                <tbody>@forelse ($byOperator as $r)<tr class="border-t border-white/5"><td class="py-1.5">{{ $r->call_sign }} · {{ $r->name }}</td><td class="text-right">{{ $r->jobs }}</td><td class="text-right">{{ $r->rating_avg ? number_format($r->rating_avg, 1) : '—' }}</td><td class="text-right">{{ $r->payouts > 0 ? rands($r->payouts, false) : '—' }}</td></tr>@empty<tr><td colspan="4" class="py-3 text-gray-500">No completed jobs in this period.</td></tr>@endforelse</tbody></table>
        </x-filament::section>
        <x-filament::section heading="Bookings by status">
            <table class="w-full text-sm"><tbody>@forelse ($byStatus as $status => $n)<tr class="border-t border-white/5"><td class="py-1.5">{{ \App\Enums\BookingStatus::tryFrom($status)?->getLabel() ?? $status }}</td><td class="text-right">{{ $n }}</td></tr>@empty<tr><td class="py-3 text-gray-500">No bookings.</td></tr>@endforelse</tbody></table>
        </x-filament::section>
        <x-filament::section heading="Top clients">
            <table class="w-full text-sm"><tbody>@forelse ($topClients as $r)<tr class="border-t border-white/5"><td class="py-1.5">{{ $r->name }}</td><td class="text-right">{{ $r->bookings }} jobs</td><td class="text-right">{{ rands($r->spend, false) }}</td></tr>@empty<tr><td class="py-3 text-gray-500">No completed jobs.</td></tr>@endforelse</tbody></table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
