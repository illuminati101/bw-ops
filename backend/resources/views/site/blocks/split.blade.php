@php($reverse = ! empty($data['reverse']))
<section class="wrap py-20 md:py-28">
    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
        <div class="{{ $reverse ? 'lg:order-2' : '' }}">
            @if (! empty($data['eyebrow']))<p class="mb-4 text-mist">{{ $data['eyebrow'] }}</p>@endif
            @if (! empty($data['heading']))<h2 class="text-display-m">{{ $data['heading'] }}</h2>@endif
            <div class="prose-noir mt-6">@foreach (preg_split('/\n\s*\n/', (string) ($data['body'] ?? '')) as $para)<p>{{ $para }}</p>@endforeach</div>
            @if (! empty($data['bullets']))
                <ul class="mt-8 grid gap-3 sm:grid-cols-2">
                    @foreach ($data['bullets'] as $b)<li class="flex gap-3"><x-glyph name="check" class="mt-1 size-4 shrink-0 text-champagne" stroke="2" /><span>{{ $b }}</span></li>@endforeach
                </ul>
            @endif
            @if (! empty($data['cta_label']))<a href="{{ $data['cta_url'] ?? '#' }}" class="btn btn-line mt-10">{{ $data['cta_label'] }}</a>@endif
        </div>
        <div class="{{ $reverse ? 'lg:order-1' : '' }}">
            @if (($data['visual'] ?? 'image') === 'phone')
                @include('site.partials.phone-mock')
            @else
                <div class="overflow-hidden rounded-[22px] border border-bone/10">
                    <img src="{{ \App\Support\Media::url($data['image'] ?? null, 'images/operator-briefing.jpg') }}" alt="" class="aspect-[4/5] w-full object-cover md:aspect-[5/4]" loading="lazy">
                </div>
            @endif
        </div>
    </div>
</section>
