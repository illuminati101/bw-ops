@props(['field', 'value' => null, 'placeholder' => 'Search for an address or place', 'index' => null, 'label' => null, 'locate' => false, 'clearable' => false])
<div x-data="{ q: @js($value['address'] ?? ''), results: [], active: -1, busy: false, locating: false,
        async search() { if (this.q.trim().length < 3) { this.results = []; return; } this.busy = true; this.results = await $wire.searchPlaces(this.q); this.active = -1; this.busy = false; },
        choose(r) { this.q = r.address; this.results = []; $wire.setPlace(@js($field), r, @js($index)); },
        locate() { if (!navigator.geolocation) return; this.locating = true; navigator.geolocation.getCurrentPosition(p => { this.locating = false; $wire.useMyLocation(p.coords.latitude, p.coords.longitude).then(() => { this.q = $wire.pickup?.address ?? this.q }); }, () => this.locating = false, { enableHighAccuracy: true, timeout: 10000 }); } }"
     x-on:click.outside="results = []" class="relative">
    @if ($label)<label class="label">{{ $label }}</label>@endif
    <div class="relative">
        <x-glyph name="map-pin" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-champagne" />
        <input x-model="q" x-on:input.debounce.350ms="search()" x-on:keydown.down.prevent="if (results.length) active = (active + 1) % results.length"
               x-on:keydown.up.prevent="if (results.length) active = (active - 1 + results.length) % results.length"
               x-on:keydown.enter.prevent="if (active >= 0) choose(results[active])"
               type="text" autocomplete="off" placeholder="{{ $placeholder }}" class="field pl-10 {{ $locate || $clearable ? 'pr-28' : '' }}">
        <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
            <span x-show="busy" class="text-xs text-ash">…</span>
            @if ($locate)<button type="button" x-on:click="locate()" class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-mist hover:text-bone"><x-glyph name="locate-fixed" class="size-3.5" /><span x-text="locating ? 'Locating…' : 'Near me'"></span></button>@endif
            @if ($clearable && $value)<button type="button" wire:click="clearPlace('{{ $field }}')" x-on:click="q = ''" class="rounded px-2 py-1 text-xs text-ash hover:text-bone">Clear</button>@endif
        </div>
    </div>
    <ul x-cloak x-show="results.length" class="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-xl border border-bone/10 bg-carbon shadow-2xl">
        <template x-for="(r, i) in results" :key="i">
            <li><button type="button" x-on:click="choose(r)" :class="i === active ? 'bg-graphite' : ''" class="flex w-full flex-col items-start px-4 py-2.5 text-left hover:bg-graphite">
                <span class="text-sm font-medium" x-text="r.label"></span><span class="text-xs text-ash" x-text="r.address"></span>
            </button></li>
        </template>
    </ul>
</div>
