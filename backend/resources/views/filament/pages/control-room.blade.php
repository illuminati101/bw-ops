<x-filament-panels::page>
    <div x-data="controlRoom(@js(route('staff.control-room.data')))" x-init="init()" class="grid gap-4 xl:grid-cols-3"
         x-on:bw-realtime.window="refresh(); $wire.$refresh()">
        <div class="xl:col-span-2 space-y-3">
            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-400">
                <span class="bw-pill" style="background:rgba(201,168,106,.15);color:#C9A86A">● Free</span>
                <span class="bw-pill" style="background:rgba(56,189,248,.15);color:#7dd3fc">● On a job</span>
                <span class="bw-pill" style="background:rgba(113,113,122,.2);color:#a1a1aa">● Offline / stale</span>
                <span class="bw-pill" style="background:rgba(245,245,242,.12);color:#f5f5f2">■ Pickup</span>
                <span class="bw-pill" style="background:rgba(225,29,72,.2);color:#fda4af">● SOS</span>
                <span class="ml-auto">{{ $online }} operator(s) online · updated <span x-text="updatedAt"></span></span>
            </div>
            <div id="bw-map" class="bw-map" wire:ignore></div>
        </div>

        <div class="space-y-4" wire:poll.6s>
            @if ($sos->isNotEmpty())
                <x-filament::section class="bw-sos-flash" style="border-color: rgba(225,29,72,.6)">
                    <x-slot name="heading"><span style="color:#fda4af">SOS · {{ $sos->count() }} active</span></x-slot>
                    <div class="space-y-3">
                        @foreach ($sos as $s)
                            <div class="rounded-lg border border-rose-500/40 p-3">
                                <div class="font-semibold">{{ $s->user?->name }} <span class="text-xs text-gray-400">({{ $s->raised_by_role }})</span></div>
                                <div class="text-xs text-gray-400">{{ $s->created_at->diffForHumans() }} · {{ $s->booking?->reference ?? 'no booking' }} · {{ $s->status }}</div>
                                @if ($s->lat)<a class="text-xs underline" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ $s->lat }},{{ $s->lng }}">Open location</a>@endif
                                @if(str_contains((string) $s->notes, 'DURESS'))<div class="mt-1 text-xs font-bold text-rose-400">DURESS PIN ENTERED: treat as live</div>@endif
                                @if ($canRespond)
                                    <div class="mt-2 flex gap-2">
                                        @if ($s->status === 'open')<x-filament::button size="xs" color="warning" wire:click="acknowledge({{ $s->id }})">Acknowledge</x-filament::button>@endif
                                        <x-filament::button size="xs" color="success" wire:click="resolve({{ $s->id }}, 'resolved')">Resolved</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="resolve({{ $s->id }}, 'false_alarm')">False alarm</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @foreach ([
                ['Finding an operator', $searching, 'warning', 'No operator has accepted yet'],
                ['Awaiting approval', $pending, 'warning', null],
                ['Live & starting soon', $live, 'primary', null],
                ['Confirmed, no operator (next 24h)', $unassigned, 'danger', null],
                ['Not reconfirmed', $notReconfirmed, 'danger', 'Call the client'],
            ] as [$title, $items, $color, $hint])
                @if ($items->isNotEmpty())
                    <x-filament::section :heading="$title.' · '.$items->count()" collapsible>
                        <div class="divide-y divide-white/5">
                            @foreach ($items as $b)
                                <div class="flex items-center justify-between gap-2 py-2">
                                    <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $b]) }}" class="min-w-0">
                                        <div class="text-sm font-semibold">{{ $b->reference }} · {{ $b->starts_at->format('D H:i') }}</div>
                                        <div class="truncate text-xs text-gray-400">{{ $b->client?->name }} · {{ $b->service?->name ?? '' }} @if($b->relationLoaded('activeAssignments') && $b->activeAssignments->isNotEmpty()) · {{ $b->activeAssignments->map(fn ($a) => $a->operator->call_sign)->implode(', ') }} @endif</div>
                                        @if ($hint)<div class="text-xs text-amber-400">{{ $hint }}</div>@endif
                                    </a>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-filament::badge :color="$b->status->getColor()">{{ $b->status->getLabel() }}</x-filament::badge>
                                        @if ($b->status === \App\Enums\BookingStatus::PendingApproval && $canApprove)
                                            <x-filament::button size="xs" wire:click="approve({{ $b->id }})">Approve</x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            @endforeach

            @if ($sos->isEmpty() && $searching->isEmpty() && $pending->isEmpty() && $live->isEmpty() && $unassigned->isEmpty() && $notReconfirmed->isEmpty())
                <x-filament::section><p class="text-sm text-gray-400">All quiet. Nothing needs attention right now.</p></x-filament::section>
            @endif
        </div>
    </div>

    <script>
        function controlRoom(url) {
            return {
                map: null, layers: null, updatedAt: '…', fitted: false,
                init() {
                    const boot = () => {
                        if (!window.L) { return setTimeout(boot, 200); }
                        this.map = L.map('bw-map', { zoomControl: true }).setView([-33.9249, 18.4241], 11);
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(this.map);
                        this.layers = L.layerGroup().addTo(this.map);
                        this.refresh();
                        setInterval(() => this.refresh(), 4000);
                    };
                    boot();
                },
                icon(cls, label) { return L.divIcon({ className: '', html: `<div class="bw-marker ${cls}">${label}</div>`, iconSize: [30, 30], iconAnchor: [15, 15] }); },
                async refresh() {
                    if (!this.map) return;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const d = await res.json();
                    this.layers.clearLayers();
                    const pts = [];
                    d.bookings.forEach(b => { pts.push([b.lat, b.lng]); L.marker([b.lat, b.lng], { icon: this.icon('bw-marker--booking', 'P') }).bindPopup(`<b>${b.reference}</b><br>${b.status_label}<br>${b.client} · ${b.starts_at}<br>${b.pickup}<br>${b.operators || 'No operator yet'}<br><a href="${b.url}">Open booking</a>`).addTo(this.layers); });
                    d.operators.forEach(o => { pts.push([o.lat, o.lng]); const cls = !o.online || o.stale ? 'bw-marker--offline' : (o.busy ? 'bw-marker--busy' : ''); L.marker([o.lat, o.lng], { icon: this.icon(cls, (o.call_sign || '').replace('BW-', '')) }).bindPopup(`<b>${o.call_sign}</b> · ${o.name}<br>${o.online ? (o.busy ? 'On a job' : 'Online, free') : 'Offline'}<br>${o.vehicle || ''}<br>Seen ${o.seen || 'never'}`).addTo(this.layers); });
                    d.sos.forEach(s => { if (s.lat) { pts.push([s.lat, s.lng]); L.marker([s.lat, s.lng], { icon: this.icon('bw-marker--sos', 'SOS'), zIndexOffset: 1000 }).bindPopup(`<b>SOS</b> · ${s.who} (${s.role})<br>${s.since}<br>${s.booking || ''}`).addTo(this.layers); } });
                    if (!this.fitted && pts.length) { this.map.fitBounds(pts, { padding: [40, 40], maxZoom: 13 }); this.fitted = true; }
                    this.updatedAt = d.at;
                },
            };
        }
    </script>
</x-filament-panels::page>
