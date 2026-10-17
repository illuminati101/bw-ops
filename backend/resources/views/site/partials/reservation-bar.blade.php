<form action="{{ route('book') }}" method="get" x-data="reservationBar()" @click.outside="results = []"
      class="hero-line glass relative mt-12 grid max-w-4xl gap-3 p-3 md:grid-cols-[1fr_auto_auto] md:items-center md:p-3.5">
    <div class="relative">
        <label for="rb-pickup" class="sr-only">Pickup address</label>
        <x-glyph name="map-pin" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-champagne" />
        <input id="rb-pickup" x-model="q" @input.debounce.350ms="search()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter="pickActive($event)"
               type="text" autocomplete="off" placeholder="Where should we collect you?" class="field h-14 border-transparent bg-transparent pl-12 pr-28 text-lg md:bg-graphite/40" role="combobox" aria-autocomplete="list" :aria-expanded="results.length > 0">
        <button type="button" @click="locate()" class="absolute right-2 top-1/2 inline-flex -translate-y-1/2 items-center gap-1.5 rounded px-2.5 py-1.5 text-sm text-mist hover:text-bone">
            <x-glyph name="locate-fixed" class="size-4" /><span x-text="locating ? 'Locating…' : 'Near me'"></span>
        </button>
        <input type="hidden" name="pickup" :value="pickup ? JSON.stringify(pickup) : ''">
        <ul x-cloak x-show="results.length" class="absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-xl border border-bone/10 bg-carbon shadow-2xl" role="listbox">
            <template x-for="(r, i) in results" :key="i">
                <li><button type="button" @click="choose(r)" :class="i === active ? 'bg-graphite' : ''" class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-graphite">
                    <span class="font-medium" x-text="r.label"></span><span class="text-sm text-ash" x-text="r.address"></span>
                </button></li>
            </template>
        </ul>
    </div>
    <div class="seg justify-self-start" role="group" aria-label="When">
        <button type="button" :aria-pressed="mode === 'on_demand'" @click="mode = 'on_demand'">Now</button>
        <button type="button" :aria-pressed="mode === 'advance'" @click="mode = 'advance'">Schedule</button>
    </div>
    <input type="hidden" name="mode" :value="mode">
    <button type="submit" class="btn btn-gold h-14 px-7 text-base">Get a price</button>
</form>
@once
@push('scripts')
<script>
function reservationBar() {
    return {
        q: '', results: [], active: -1, pickup: null, mode: 'advance', locating: false,
        async search() {
            this.pickup = null;
            if (this.q.trim().length < 3) { this.results = []; return; }
            try { const r = await fetch('/api/v1/places/search?q=' + encodeURIComponent(this.q)); this.results = (await r.json()).results || []; this.active = -1; } catch (e) { this.results = []; }
        },
        move(d) { if (this.results.length) this.active = (this.active + d + this.results.length) % this.results.length; },
        pickActive(e) { if (this.active >= 0) { e.preventDefault(); this.choose(this.results[this.active]); } },
        async choose(r) {
            if (r.lat == null && r.place_id) { try { const d = await (await fetch('/api/v1/places/details?place_id=' + encodeURIComponent(r.place_id))).json(); if (d.place) r = d.place; } catch (e) {} }
            this.pickup = { address: r.address, lat: r.lat, lng: r.lng }; this.q = r.label === r.address ? r.address : r.label + ', ' + r.address.split(',').slice(1, 3).join(',').trim(); this.results = [];
        },
        locate() {
            if (!navigator.geolocation) return;
            this.locating = true;
            navigator.geolocation.getCurrentPosition(async (p) => {
                const lat = p.coords.latitude, lng = p.coords.longitude;
                let address = 'Current location';
                try { address = (await (await fetch(`/api/v1/places/reverse?lat=${lat}&lng=${lng}`)).json()).address || address; } catch (e) {}
                this.pickup = { address, lat, lng }; this.q = address; this.locating = false; this.mode = 'on_demand';
            }, () => { this.locating = false; }, { enableHighAccuracy: true, timeout: 10000 });
        },
    };
}
</script>
@endpush
@endonce
