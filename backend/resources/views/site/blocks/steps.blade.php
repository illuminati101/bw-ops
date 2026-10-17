<section class="border-y hairline bg-carbon/60">
    <div class="wrap py-20 md:py-28">
        <div class="mb-14 max-w-2xl">
            @if (! empty($data['eyebrow']))<p class="mb-4 text-mist">{{ $data['eyebrow'] }}</p>@endif
            @if (! empty($data['heading']))<h2 class="text-display-l">{{ $data['heading'] }}</h2>@endif
        </div>
        <ol class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($data['items'] ?? [] as $i => $step)
                <li class="relative">
                    <div class="mb-6 flex items-center gap-4">
                        <span class="font-display text-5xl leading-none text-champagne">{{ $i + 1 }}</span>
                        <span class="h-px flex-1 bg-bone/10"></span>
                    </div>
                    <h3 class="font-sans text-lg font-semibold">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-ash">{{ $step['body'] ?? '' }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
