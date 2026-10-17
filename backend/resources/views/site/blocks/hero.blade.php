@php($img = \App\Support\Media::url($data['image'] ?? null, 'images/hero-night-drive.jpg'))
@if (empty($data['compact']))
<section class="relative isolate flex min-h-[100svh] items-end overflow-hidden pb-14 pt-32 md:pb-20">
    <img src="{{ $img }}" alt="" class="hero-img absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
    <div class="hero-veil absolute inset-0 -z-10"></div>
    <div class="wrap">
        <div class="max-w-4xl">
            @if (! empty($data['eyebrow']))<p class="hero-line mb-6 text-[.95rem] text-mist">{{ $data['eyebrow'] }}</p>@endif
            <h1 class="hero-line text-display-xl text-bone">{{ $data['heading'] }}</h1>
            @if (! empty($data['subheading']))<p class="hero-line mt-7 max-w-xl text-lg text-mist md:text-xl">{{ $data['subheading'] }}</p>@endif
        </div>
        @include('site.partials.reservation-bar')
    </div>
</section>
@else
<section class="relative isolate overflow-hidden border-b hairline">
    @if ($img)
        <img src="{{ $img }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-70">
        <div class="hero-veil absolute inset-0 -z-10"></div>
    @endif
    <div class="wrap pb-16 pt-36 md:pb-24 md:pt-44">
        @if (! empty($data['eyebrow']))<p class="mb-5 text-[.95rem] text-mist">{{ $data['eyebrow'] }}</p>@endif
        <h1 class="max-w-4xl text-display-l">{{ $data['heading'] }}</h1>
        @if (! empty($data['subheading']))<p class="mt-6 max-w-2xl text-lg text-mist">{{ $data['subheading'] }}</p>@endif
        @if (! empty($data['primary_label']))
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ $data['primary_url'] ?? '#' }}" class="btn btn-gold">{{ $data['primary_label'] }}</a>
                @if (! empty($data['secondary_label']))<a href="{{ $data['secondary_url'] ?? '#' }}" class="btn btn-line">{{ $data['secondary_label'] }}</a>@endif
            </div>
        @endif
    </div>
</section>
@endif
