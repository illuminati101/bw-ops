<x-filament-widgets::widget>
    <x-filament::section collapsible :collapsed="$done === count($items)" heading="Launch checklist · {{ $done }} of {{ count($items) }} done" description="Everything that needs attention before 1 November.">
        <ul class="grid gap-2 md:grid-cols-2">
            @foreach ($items as [$label, $ok, $hint, $url])
                <li class="flex items-start gap-3 rounded-lg border border-white/5 p-3">
                    <span class="mt-0.5 text-lg leading-none" style="color: {{ $ok ? '#6ee7b7' : '#fbbf24' }}">{{ $ok ? '✓' : '○' }}</span>
                    <span>
                        <span class="block text-sm font-semibold">{{ $label }}</span>
                        @unless ($ok)<span class="block text-xs text-gray-400">{{ $hint }} @if($url)<a href="{{ $url }}" class="underline">Open</a>@endif</span>@endunless
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
