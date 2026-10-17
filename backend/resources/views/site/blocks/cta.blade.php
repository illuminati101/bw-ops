<section class="relative isolate my-12 overflow-hidden">
    <img src="{{ asset('images/about-skyline.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" loading="lazy">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-night via-night/80 to-night/30"></div>
    <div class="wrap py-24 md:py-36">
        <h2 class="max-w-3xl text-display-l">{{ $data['heading'] ?? '' }}</h2>
        @if (! empty($data['body']))<p class="mt-5 max-w-xl text-lg text-mist">{{ $data['body'] }}</p>@endif
        <div class="mt-10 flex flex-wrap gap-3">
            @if (! empty($data['primary_label']))<a href="{{ $data['primary_url'] ?? '#' }}" class="btn btn-gold">{{ $data['primary_label'] }}</a>@endif
            @if (! empty($data['secondary_label']))<a href="{{ $data['secondary_url'] ?? '#' }}" class="btn btn-line">{{ $data['secondary_label'] }}</a>@endif
        </div>
    </div>
</section>
