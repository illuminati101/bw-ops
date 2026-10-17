@php($services = \App\Models\Service::query()->published()->orderBy('sort')->get()->filter->isAvailable())
@php($region = \App\Models\Region::default())
<section class="wrap py-20 md:py-28">
    @if (! empty($data['heading']))
        <div class="mb-12 grid gap-6 md:grid-cols-[1fr_1fr] md:items-end">
            <div>
                @if (! empty($data['eyebrow']))<p class="mb-4 text-mist">{{ $data['eyebrow'] }}</p>@endif
                <h2 class="text-display-l">{{ $data['heading'] }}</h2>
            </div>
            @if (! empty($data['intro']))<p class="max-w-md text-ash md:justify-self-end">{{ $data['intro'] }}</p>@endif
        </div>
    @endif

    @if (empty($data['detailed']))
        <div>
            @foreach ($services as $s)
                @php($card = $s->rateCardFor($region))
                <a href="{{ route('services.show', $s->slug) }}" class="index-row group">
                    <h3>{{ $s->name }}</h3>
                    <p class="text-ash">{{ $s->summary }}</p>
                    <p class="whitespace-nowrap text-sm text-mist md:text-right">
                        @if ($s->custom_quote) Tailored quote
                        @elseif ($card) From {{ rands(max($card->minimum_charge, $card->callout_fee + $card->per_hour * $s->min_duration_minutes / 60), false) }}
                        @endif
                        <span class="ml-2 inline-block text-champagne transition-transform group-hover:translate-x-1" aria-hidden="true">›</span>
                    </p>
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-24">
            @foreach ($services as $i => $s)
                @php($card = $s->rateCardFor($region))
                <article id="{{ $s->slug }}" class="grid gap-10 border-t hairline pt-12 lg:grid-cols-[1fr_1.15fr]">
                    <div>
                        <p class="mb-3 text-mist">{{ $s->categoryLabel() }}</p>
                        <h2 class="text-display-m">{{ $s->name }}</h2>
                        @if ($s->tagline)<p class="mt-3 font-display text-2xl italic text-champagne-light">{{ $s->tagline }}</p>@endif
                        <div class="mt-8 flex flex-wrap items-center gap-4">
                            <a href="{{ route('book', ['service' => $s->slug]) }}" class="btn btn-gold">{{ $s->custom_quote ? 'Request a quote' : 'Book '.$s->name }}</a>
                            <span class="text-sm text-ash">
                                @if ($s->custom_quote) Priced to your event @elseif ($card) From {{ rands(max($card->minimum_charge, $card->callout_fee + $card->per_hour * $s->min_duration_minutes / 60), false) }} @endif
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="prose-noir">@foreach (preg_split('/\n\s*\n/', (string) $s->description) as $para)<p>{{ $para }}</p>@endforeach</div>
                        @if ($s->highlights)
                            <ul class="mt-8 grid gap-3 sm:grid-cols-2">
                                @foreach ($s->highlights as $h)
                                    <li class="flex gap-3 text-[.95rem] text-bone"><x-glyph name="check" class="mt-1 size-4 shrink-0 text-champagne" stroke="2" />{{ $h }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($s->ideal_for)<p class="mt-8 text-sm text-ash">Ideal for: {{ $s->ideal_for }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
