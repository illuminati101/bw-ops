<x-layouts.site :title="$service->name" :description="$service->summary" :overlay-header="true">
    @if ($preview ?? false)
        <div class="fixed inset-x-0 bottom-0 z-50 bg-champagne py-2 text-center text-sm font-semibold text-night">Draft preview: this is not live yet.</div>
    @endif
    <section class="relative isolate overflow-hidden border-b hairline">
        <img src="{{ \App\Support\Media::url($service->image_path, 'images/services-sedan.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-70">
        <div class="hero-veil absolute inset-0 -z-10"></div>
        <div class="wrap pb-16 pt-40 md:pb-24 md:pt-48">
            <a href="{{ route('services.index') }}" class="text-mist hover:text-bone">Services</a>
            <h1 class="mt-4 max-w-4xl text-display-l">{{ $service->name }}</h1>
            @if ($service->tagline)<p class="mt-4 font-display text-3xl italic text-champagne-light">{{ $service->tagline }}</p>@endif
            <p class="mt-6 max-w-2xl text-lg text-mist">{{ $service->summary }}</p>
            <div class="mt-10 flex flex-wrap items-center gap-4">
                <a href="{{ route('book', ['service' => $service->slug]) }}" class="btn btn-gold">{{ $service->custom_quote ? 'Request a quote' : 'Book now' }}</a>
                @if ($service->allows_on_demand && feature_enabled('on_demand'))<a href="{{ route('book', ['service' => $service->slug, 'mode' => 'on_demand']) }}" class="btn btn-line">Hail now</a>@endif
            </div>
        </div>
    </section>
    <section class="wrap grid gap-14 py-20 lg:grid-cols-[1.4fr_1fr] lg:py-28">
        <div>
            <div class="prose-noir text-lg">@foreach (preg_split('/\n\s*\n/', (string) $service->description) as $para)<p>{{ $para }}</p>@endforeach</div>
            @if ($service->highlights)
                <ul class="mt-10 grid gap-4 sm:grid-cols-2">
                    @foreach ($service->highlights as $h)<li class="flex gap-3"><x-glyph name="check" class="mt-1 size-4 shrink-0 text-champagne" stroke="2" />{{ $h }}</li>@endforeach
                </ul>
            @endif
        </div>
        <aside class="panel h-fit p-7">
            <h2 class="font-sans text-base font-semibold">Pricing</h2>
            @if ($service->custom_quote)
                <p class="mt-3 text-ash">Every detail is different, so we quote each one individually. Tell us the dates and requirements and our team will reply with a firm price.</p>
            @elseif ($card)
                <dl class="mt-4 space-y-3 text-sm">
                    @if ($card->callout_fee > 0)<div class="flex justify-between gap-4"><dt class="text-ash">Call-out</dt><dd>{{ rands($card->callout_fee, false) }}</dd></div>@endif
                    <div class="flex justify-between gap-4"><dt class="text-ash">Per hour</dt><dd>{{ rands($card->per_hour, false) }}</dd></div>
                    @if ($card->per_km > 0)<div class="flex justify-between gap-4"><dt class="text-ash">Per km (after {{ $card->included_km }} km)</dt><dd>{{ rands($card->per_km, false) }}</dd></div>@endif
                    <div class="flex justify-between gap-4"><dt class="text-ash">Minimum</dt><dd>{{ rands($card->minimum_charge, false) }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-ash">You see a firm, itemised quote before confirming. Nothing is charged until the job is done.</p>
            @endif
            @if ($service->ideal_for)<p class="mt-6 border-t hairline pt-5 text-sm text-mist">Ideal for {{ \Illuminate\Support\Str::lcfirst($service->ideal_for) }}.</p>@endif
        </aside>
    </section>
    @include('site.blocks.cta', ['data' => ['heading' => 'Ready when you are.', 'body' => 'Create an account in minutes, verify once, and book whenever you need us.', 'primary_label' => $service->custom_quote ? 'Request a quote' : 'Book '.$service->name, 'primary_url' => route('book', ['service' => $service->slug]), 'secondary_label' => 'Call the control room', 'secondary_url' => 'tel:'.preg_replace('/\s+/', '', settings('company.control_room_phone'))]])
</x-layouts.site>
