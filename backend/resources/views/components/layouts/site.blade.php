<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ isset($title) && $title ? $title.' | ' : '' }}{{ settings('company.trading_name') }}</title>
    <meta name="description" content="{{ $description ?? 'Armed chauffeurs, close protection officers and protected transfers in Cape Town. Book ahead or hail on demand, 24/7.' }}">
    <meta name="theme-color" content="#070707">
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/favicon-180.png') }}">
    <meta property="og:title" content="{{ $title ?? settings('company.trading_name') }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    <meta property="og:image" content="{{ asset('images/hero-night-drive.jpg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($realtime = auth()->check() ? \App\Support\Realtime::publicConfig() : null)
    <script>window.__realtime = {{ Js::from($realtime) }}; window.__tiles = {{ Js::from(config('bwops.integrations.map_tiles')) }};</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-dvh bg-night text-bone">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 btn btn-gold">Skip to content</a>

    <header class="{{ ($overlayHeader ?? false) ? 'absolute' : 'relative border-b hairline' }} inset-x-0 top-0 z-40">
        <div class="wrap flex h-20 items-center justify-between gap-6">
            <x-logo />
            @unless ($bare ?? false)
            <nav class="hidden items-center gap-8 text-[.95rem] text-mist lg:flex" aria-label="Main">
                @foreach (['services.index' => 'Services', 'page:about' => 'About', 'page:corporate' => 'Corporate', 'page:faq' => 'FAQ', 'page:contact' => 'Contact'] as $route => $label)
                    @php($url = str_starts_with($route, 'page:') ? route('page', substr($route, 5)) : route($route))
                    <a href="{{ $url }}" class="transition-colors hover:text-bone {{ url()->current() === $url ? 'text-bone' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('account') }}" class="btn btn-ghost hidden sm:inline-flex"><x-glyph name="user" class="size-4" />Account</a>
                @else
                    <a href="{{ route('account.login') }}" class="btn btn-ghost hidden sm:inline-flex">Sign in</a>
                @endauth
                <a href="{{ route('book') }}" class="btn btn-gold">Book</a>
                <button type="button" class="btn btn-ghost lg:hidden" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="Menu"><x-glyph name="menu" class="size-6" /></button>
            </div>
            @endunless
        </div>
        @unless ($bare ?? false)
        <div id="mobile-nav" class="hidden border-t hairline bg-night/95 backdrop-blur lg:hidden [.nav-open_&]:block">
            <nav class="wrap flex flex-col py-4 text-lg" aria-label="Mobile">
                <a class="py-3" href="{{ route('services.index') }}">Services</a>
                <a class="py-3" href="{{ route('page', 'about') }}">About</a>
                <a class="py-3" href="{{ route('page', 'corporate') }}">Corporate</a>
                <a class="py-3" href="{{ route('page', 'faq') }}">FAQ</a>
                <a class="py-3" href="{{ route('page', 'contact') }}">Contact</a>
                <a class="py-3 text-champagne" href="{{ auth()->check() ? route('account') : route('account.login') }}">{{ auth()->check() ? 'Your account' : 'Sign in' }}</a>
            </nav>
        </div>
        @endunless
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    @if ($bare ?? false)
    <footer class="border-t hairline">
        <div class="wrap flex flex-col gap-2 py-6 text-xs text-ash sm:flex-row sm:justify-between">
            <p>{{ settings('company.trading_name') }}. 24/7 control room <a class="text-mist hover:text-bone" href="tel:{{ preg_replace('/\s+/', '', settings('company.control_room_phone')) }}">{{ settings('company.control_room_phone') }}</a>.</p>
            <p><a class="hover:text-bone" href="{{ route('legal', 'privacy') }}">Privacy</a></p>
        </div>
    </footer>
    @else
    <footer class="mt-24 border-t hairline">
        <div class="wrap grid gap-12 py-16 md:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
            <div class="space-y-5">
                <x-logo />
                <p class="max-w-xs text-sm text-ash">Armed chauffeurs and close protection across Cape Town and the Winelands. Day or night.</p>
            </div>
            <div>
                <h2 class="mb-4 font-sans text-sm font-semibold text-bone">Services</h2>
                <ul class="space-y-2 text-sm text-ash">
                    @foreach (\App\Models\Service::query()->published()->orderBy('sort')->limit(6)->get(['name', 'slug']) as $s)
                        <li><a class="hover:text-bone" href="{{ route('services.show', $s->slug) }}">{{ $s->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-sans text-sm font-semibold text-bone">Company</h2>
                <ul class="space-y-2 text-sm text-ash">
                    <li><a class="hover:text-bone" href="{{ route('page', 'about') }}">About us</a></li>
                    <li><a class="hover:text-bone" href="{{ route('page', 'corporate') }}">Corporate accounts</a></li>
                    <li><a class="hover:text-bone" href="{{ route('page', 'careers') }}">Careers</a></li>
                    <li><a class="hover:text-bone" href="{{ route('page', 'faq') }}">FAQ</a></li>
                    <li><a class="hover:text-bone" href="{{ route('legal', 'terms') }}">Terms of Service</a></li>
                    <li><a class="hover:text-bone" href="{{ route('legal', 'privacy') }}">Privacy Policy</a></li>
                    <li><a class="hover:text-bone" href="{{ route('legal', 'cancellation') }}">Cancellation Policy</a></li>
                </ul>
            </div>
            <div class="space-y-4 text-sm">
                <h2 class="font-sans text-sm font-semibold text-bone">24/7 control room</h2>
                <a href="tel:{{ preg_replace('/\s+/', '', settings('company.control_room_phone')) }}" class="block font-display text-3xl text-champagne-light hover:text-champagne">{{ settings('company.control_room_phone') }}</a>
                <a href="mailto:{{ settings('company.email') }}" class="block text-ash hover:text-bone">{{ settings('company.email') }}</a>
                <p class="text-ash">In an emergency, also call 10111.</p>
            </div>
        </div>
        <div class="border-t hairline">
            <div class="wrap flex flex-col gap-2 py-6 text-xs text-ash md:flex-row md:items-center md:justify-between">
                <p>&copy; {{ now()->year }} {{ settings('company.legal_name') }}. Registration {{ settings('company.registration_number') }}. PSIRA {{ settings('company.psira_number') }}.</p>
                <p>{{ settings('company.physical_address') }}</p>
            </div>
        </div>
    </footer>
    @endif
    @livewireScripts
    @stack('scripts')
</body>
</html>
