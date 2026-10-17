<x-layouts.site :title="'Trip with '.settings('company.trading_name')" :bare="true">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="wrap py-8 md:py-12" x-data="tripShare(@js($data), @js(route('trip.share.data', $token)))">
        <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="overflow-hidden rounded-xl border border-bone/10">
                <div x-ref="map" class="map-noir h-[55vh] min-h-80" role="img" aria-label="Map of the trip"></div>
            </div>
            <aside class="space-y-6">
                <div>
                    <p class="text-sm text-ash"><span x-text="d.service"></span> for <span x-text="d.for"></span></p>
                    <h1 class="mt-1 font-display text-4xl leading-tight" x-text="headline()"></h1>
                    <p class="mt-2 text-mist" x-show="d.eta_minutes" x-text="'Arriving in about ' + d.eta_minutes + ' min'"></p>
                </div>
                <template x-if="d.operator">
                    <div class="rounded-xl border border-bone/10 p-4">
                        <p class="font-semibold"><span x-text="d.operator.first_name"></span> <span class="font-normal text-ash" x-text="d.operator.call_sign ? '(' + d.operator.call_sign + ')' : ''"></span></p>
                        <p class="text-sm text-mist" x-show="d.operator.vehicle"><span x-text="d.operator.vehicle"></span> <span class="ml-1 rounded border border-bone/20 px-1.5 py-0.5 font-medium text-bone" x-text="d.operator.registration"></span></p>
                    </div>
                </template>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-ash">Scheduled</dt><dd x-text="d.starts_at"></dd></div>
                    <div x-show="d.pickup_area"><dt class="text-ash">From</dt><dd x-text="d.pickup_area"></dd></div>
                    <div x-show="d.dropoff_area"><dt class="text-ash">To</dt><dd x-text="d.dropoff_area"></dd></div>
                </dl>
                <p class="border-t hairline pt-5 text-sm text-ash">Our control room monitors every trip around the clock. If you are worried about this trip, call <a class="text-champagne-light" :href="'tel:' + (d.control_room || '').replace(/\s+/g, '')" x-text="d.control_room"></a>. This link stops working at <span x-text="d.expires_at"></span>.</p>
            </aside>
        </div>
    </div>
    @push('scripts')
    <script>
        function tripShare(initial, url) {
            return {
                d: initial, m: null, timer: null,
                init() {
                    this.$nextTick(() => { this.m = bwTrackMap(this.$refs.map, this.d.map); });
                    this.timer = setInterval(() => this.poll(), 10000);
                },
                async poll() {
                    if (document.hidden || this.d.closed) return;
                    try {
                        const r = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (r.status === 410) { location.reload(); return; }
                        this.d = await r.json();
                        this.m.update(this.d.map);
                    } catch (e) {}
                },
                headline() {
                    const n = this.d.operator ? this.d.operator.first_name : 'The operator';
                    return ({ assigned: n + ' is assigned', en_route: n + ' is on the way', arrived: n + ' has arrived', in_progress: 'Under way', completed: 'Trip completed', cancelled: 'Trip cancelled', searching: 'Finding an operator' })[this.d.status] || this.d.status_label;
                },
            };
        }
    </script>
    @endpush
</x-layouts.site>
