<section class="wrap py-24 md:py-32">
    <div class="grid gap-14 lg:grid-cols-[1fr_1.6fr]">
        <div>
            @if (! empty($data['eyebrow']))<p class="mb-4 text-mist">{{ $data['eyebrow'] }}</p>@endif
            @if (! empty($data['heading']))<h2 class="text-display-l">{{ $data['heading'] }}</h2>@endif
        </div>
        <div class="grid gap-x-12 gap-y-12 sm:grid-cols-2">
            @foreach ($data['items'] ?? [] as $item)
                <div>
                    <div class="mb-5 flex items-center gap-4">
                        <span class="grid size-11 place-items-center rounded-full border border-champagne/40 text-champagne"><x-glyph :name="$item['icon'] ?? 'shield'" class="size-5" /></span>
                        <span class="h-px flex-1 bg-gradient-to-r from-champagne/30 to-transparent"></span>
                    </div>
                    <h3 class="font-sans text-lg font-semibold text-bone">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-ash">{{ $item['body'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
