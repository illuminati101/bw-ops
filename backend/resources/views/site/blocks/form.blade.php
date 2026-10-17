<section id="form" class="wrap py-16 md:py-24">
    <div class="grid gap-12 lg:grid-cols-[1fr_1.4fr]">
        <div>
            @if (! empty($data['heading']))<h2 class="text-display-m">{{ $data['heading'] }}</h2>@endif
            @if (! empty($data['intro']))<p class="mt-5 max-w-md text-ash">{{ $data['intro'] }}</p>@endif
        </div>
        <div class="panel p-6 md:p-10">
            @include('site.partials.form-'.($data['form'] ?? 'contact'))
        </div>
    </div>
</section>
