@php($faqs = \App\Models\Faq::query()->published()->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))->orderBy('sort')->get()->groupBy('category'))
<section class="wrap py-16 md:py-24">
    @if (! empty($data['heading']))<h2 class="mb-10 text-display-m">{{ $data['heading'] }}</h2>@endif
    <div class="grid gap-16 lg:grid-cols-[14rem_1fr]">
        <nav class="hidden lg:block" aria-label="FAQ topics">
            <ul class="sticky top-8 space-y-3 text-mist">
                @foreach ($faqs->keys() as $cat)<li><a class="hover:text-bone" href="#{{ \Illuminate\Support\Str::slug($cat) }}">{{ $cat }}</a></li>@endforeach
            </ul>
        </nav>
        <div class="space-y-16">
            @foreach ($faqs as $cat => $items)
                <div id="{{ \Illuminate\Support\Str::slug($cat) }}" class="scroll-mt-8">
                    <h3 class="mb-4 font-display text-3xl text-champagne-light">{{ $cat }}</h3>
                    <div class="divide-y divide-bone/10 border-y border-bone/10">
                        @foreach ($items as $f)
                            <details class="group py-5">
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-lg font-medium text-bone [&::-webkit-details-marker]:hidden">
                                    {{ $f->question }}
                                    <x-glyph name="plus" class="mt-1 size-5 shrink-0 text-champagne transition-transform duration-300 group-open:rotate-45" />
                                </summary>
                                <p class="mt-3 max-w-[66ch] text-ash">{{ $f->answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
