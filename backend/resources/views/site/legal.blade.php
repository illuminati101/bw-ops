<x-layouts.site :title="$page->title" :description="$page->meta_description">
    <article class="wrap py-16 md:py-24">
        <header class="mb-12 max-w-3xl border-b hairline pb-10">
            <p class="mb-4 text-mist">Legal</p>
            <h1 class="text-display-l">{{ $page->title }}</h1>
            <p class="mt-4 text-sm text-ash">Last published {{ $page->published_at?->format('j F Y') }}</p>
        </header>
        <div class="legal max-w-3xl">{!! $html !!}</div>
    </article>
    @push('head')
    <style>
        .legal { color: var(--color-mist); }
        .legal h2 { font-size: 1.9rem; color: var(--color-bone); margin: 3rem 0 1rem; }
        .legal h3 { font-size: 1.35rem; color: var(--color-bone); margin: 2rem 0 .75rem; }
        .legal p, .legal ul, .legal ol, .legal table { margin: 0 0 1.1rem; }
        .legal ul { list-style: disc; padding-left: 1.3rem; } .legal ol { list-style: decimal; padding-left: 1.3rem; }
        .legal li { margin: .35rem 0; }
        .legal strong { color: var(--color-bone); font-weight: 600; }
        .legal a { color: var(--color-champagne-light); text-decoration: underline; }
        .legal table { width: 100%; border-collapse: collapse; font-size: .95rem; }
        .legal th, .legal td { text-align: left; padding: .6rem .8rem; border-bottom: 1px solid rgb(237 232 221 / .1); vertical-align: top; }
        .legal th { color: var(--color-bone); font-weight: 600; }
        .legal td:first-child { color: var(--color-bone); width: 40%; }
    </style>
    @endpush
</x-layouts.site>
