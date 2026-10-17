<section class="wrap py-20 md:py-28">
    <div class="grid gap-10 lg:grid-cols-[1fr_1.3fr]">
        <div>
            @if (! empty($data['eyebrow']))<p class="mb-4 text-mist">{{ $data['eyebrow'] }}</p>@endif
            @if (! empty($data['heading']))<h2 class="text-display-m">{{ $data['heading'] }}</h2>@endif
        </div>
        <div class="prose-noir text-lg">@foreach (preg_split('/\n\s*\n/', (string) ($data['body'] ?? '')) as $para)<p>{{ $para }}</p>@endforeach</div>
    </div>
</section>
