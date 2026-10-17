<section class="wrap grid min-h-[70vh] items-center gap-16 py-16 lg:grid-cols-[1.1fr_1fr]">
    <div class="hidden lg:block">
        <h1 class="text-display-l">One number. Every booking.</h1>
        <p class="mt-6 max-w-md text-lg text-ash">Sign in with your mobile number. We'll send you a one-time code. There's no password to remember, or to steal.</p>
        <ul class="mt-10 space-y-4 text-mist">
            <li class="flex gap-3"><x-glyph name="lock" class="mt-1 size-5 text-champagne" />Your number is never shown to operators.</li>
            <li class="flex gap-3"><x-glyph name="credit-card" class="mt-1 size-5 text-champagne" />Nothing is charged until a job is complete.</li>
            <li class="flex gap-3"><x-glyph name="shield-check" class="mt-1 size-5 text-champagne" />The same account works on the website and in the app.</li>
        </ul>
    </div>

    <div class="panel w-full max-w-md justify-self-center p-7 md:p-10">
        @if ($step === 'phone')
            <h2 class="text-display-s">Sign in or create an account</h2>
            <form wire:submit="sendCode" class="mt-8 space-y-5">
                <div>
                    <label class="label" for="phone">Mobile number</label>
                    <input id="phone" wire:model="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="082 123 4567" class="field text-lg" autofocus>
                    @error('phone')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-gold w-full" wire:loading.attr="disabled">Send code</button>
                <p class="text-center text-xs text-ash">South African numbers can start with 0. International numbers need the country code, e.g. +44.</p>
            </form>
        @elseif ($step === 'code')
            <h2 class="text-display-s">Enter your code</h2>
            <p class="mt-2 text-ash">Sent by SMS to {{ \App\Support\PhoneNumber::display($phone) }}.</p>
            @if ($devCode)
                <div class="mt-5 rounded-lg border border-champagne/40 bg-champagne/10 p-3 text-sm text-champagne-light">Test mode: SMS isn't connected yet, so your code is <strong class="tracking-[.3em]">{{ $devCode }}</strong>.</div>
            @endif
            <form wire:submit="verify" class="mt-6 space-y-5">
                <div>
                    <label class="label" for="code">6-digit code</label>
                    <input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="field text-center text-2xl tracking-[.5em]" autofocus>
                    @error('code')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-gold w-full" wire:loading.attr="disabled">Continue</button>
                <div class="flex justify-between text-sm">
                    <button type="button" wire:click="back" class="text-ash hover:text-bone">Change number</button>
                    <button type="button" wire:click="sendCode" class="text-ash hover:text-bone">Send a new code</button>
                </div>
            </form>
        @else
            <h2 class="text-display-s">A few details</h2>
            <p class="mt-2 text-ash">We use your email for confirmations and receipts.</p>
            <form wire:submit="saveProfile" class="mt-6 space-y-5">
                <div><label class="label" for="name">Full name</label><input id="name" wire:model="name" class="field" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="email">Email</label><input id="email" type="email" wire:model="email" class="field" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-3 text-sm text-mist">
                    <input type="checkbox" wire:model="terms" class="mt-1 accent-champagne">
                    <span>I accept the <a class="underline" target="_blank" href="{{ route('legal', 'terms') }}">Terms of Service</a>, including the clauses in bold that limit liability, and the <a class="underline" target="_blank" href="{{ route('legal', 'cancellation') }}">Cancellation Policy</a>. I've read the <a class="underline" target="_blank" href="{{ route('legal', 'privacy') }}">Privacy Policy</a>.</span>
                </label>
                @error('terms')<p class="error">{{ $message }}</p>@enderror
                <label class="flex items-start gap-3 text-sm text-mist"><input type="checkbox" wire:model="marketing" class="mt-1 accent-champagne"><span>Send me occasional news and offers. You can unsubscribe at any time.</span></label>
                <button class="btn btn-gold w-full">Continue</button>
            </form>
        @endif
    </div>
</section>
