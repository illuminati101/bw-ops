<x-layouts.site :title="$page->slug === 'home' ? null : $page->title" :description="$page->meta_description" :overlay-header="($page->blocks[0]['type'] ?? null) === 'hero'">
    @if ($preview ?? false)
        <div class="fixed inset-x-0 bottom-0 z-50 bg-champagne py-2 text-center text-sm font-semibold text-night">Draft preview: this is not live yet.</div>
    @endif
    @foreach ($page->blocks ?? [] as $block)
        @includeIf('site.blocks.'.$block['type'], ['data' => $block['data'] ?? []])
    @endforeach
</x-layouts.site>
