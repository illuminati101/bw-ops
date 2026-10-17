@props(['active' => null, 'heading' => null, 'sub' => null])
<div class="wrap py-10 md:py-14">
    <div class="grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="min-w-0 lg:sticky lg:top-8 lg:h-fit">
            <nav class="flex gap-1 overflow-x-auto pb-2 text-[.95rem] lg:flex-col lg:overflow-visible" aria-label="Account">
                @foreach (['account' => ['Bookings', 'calendar'], 'book' => ['New booking', 'plus'], 'account.payment' => ['Payment', 'credit-card'], 'account.profile' => ['Profile & safety', 'user'], 'account.verify' => ['Verification', 'id-card']] as $route => [$label, $icon])
                    <a href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-3 rounded-md px-3 py-2.5 transition-colors', 'bg-graphite text-bone' => $active === $route, 'text-ash hover:text-bone' => $active !== $route])>
                        <x-glyph :name="$icon" class="size-4" />{{ $label }}
                    </a>
                @endforeach
                <form method="post" action="{{ route('account.logout') }}" class="shrink-0 lg:mt-4 lg:border-t lg:hairline lg:pt-4">@csrf<button class="flex items-center gap-3 rounded-md px-3 py-2.5 text-ash hover:text-bone"><x-glyph name="log-out" class="size-4" />Sign out</button></form>
            </nav>
        </aside>
        <div class="min-w-0">
            @if ($heading)
                <header class="mb-8">
                    <h1 class="text-display-m">{{ $heading }}</h1>
                    @if ($sub)<p class="mt-2 text-ash">{{ $sub }}</p>@endif
                </header>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
