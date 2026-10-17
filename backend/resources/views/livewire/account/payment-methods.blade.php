<x-account-shell active="account.payment" heading="Payment" sub="You are only charged after a job is complete. A saved card secures your bookings.">
    <div class="max-w-3xl space-y-8">
        @if ($flash)<div class="rounded-lg border border-champagne/40 bg-champagne/[.06] px-4 py-3 text-sm" role="status">{{ $flash }}</div>@endif
        @if ($error)<div class="rounded-lg border border-red-500/40 bg-red-950/30 px-4 py-3 text-sm" role="alert">{{ $error }}</div>@endif

        @if ($returnTo === 'book' && $cards->isNotEmpty())
            <div class="flex flex-col gap-4 rounded-xl border border-champagne/40 bg-champagne/[.06] p-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold">Your card is saved. Ready to finish your booking?</p>
                <a href="{{ route('book', ['resume' => 1]) }}" class="btn btn-gold shrink-0">Back to your booking</a>
            </div>
        @endif

        <section>
            <h2 class="font-sans text-lg font-semibold">Saved cards</h2>
            <ul class="mt-4 divide-y divide-bone/10 rounded-xl border border-bone/10">
                @forelse ($cards as $card)
                    <li class="flex flex-wrap items-center justify-between gap-4 px-5 py-4" wire:key="card-{{ $card->id }}">
                        <div class="flex items-center gap-4">
                            <span class="grid h-10 w-14 place-items-center rounded-md border border-bone/15 bg-graphite"><x-glyph name="credit-card" class="size-5 text-champagne" /></span>
                            <div>
                                <p class="font-medium">{{ $card->label() }}</p>
                                <p class="text-sm text-ash">@if ($card->is_default) Default card @endif @if ($card->isExpired()) <span class="text-red-300">Expired</span> @endif @if ($card->bank) {{ $card->bank }} @endif</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @unless ($card->is_default)<button type="button" wire:click="makeDefault({{ $card->id }})" class="btn btn-ghost">Make default</button>@endunless
                            <button type="button" wire:click="remove({{ $card->id }})" wire:confirm="Remove this card?" class="btn btn-ghost !px-3" aria-label="Remove card"><x-glyph name="trash-2" class="size-4" /></button>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-ash">No cards saved yet.</li>
                @endforelse
            </ul>
            <div class="mt-5">
                @if ($needsEmail)
                    <p class="mb-3 text-sm text-mist">Add an email address in <a class="underline" href="{{ route('account.profile') }}">your profile</a> first. Our payment provider sends card confirmations to it.</p>
                @endif
                <button type="button" wire:click="addCard" wire:loading.attr="disabled" class="btn btn-gold" @disabled($needsEmail)>
                    <span wire:loading.remove wire:target="addCard">{{ $cards->isEmpty() ? 'Add a card' : 'Add another card' }}</span><span wire:loading wire:target="addCard">One moment…</span>
                </button>
            </div>
        </section>

        <section class="space-y-3 border-t hairline pt-8 text-sm text-ash">
            <h2 class="font-sans text-base font-semibold text-bone">How payment works</h2>
            @if ($recordOnly)
                <p>Online card payments are being connected. Until then we keep a card-on-file record, and our team settles each booking with you after the job by card machine or EFT.</p>
            @else
                <p>To save your card, our payment provider Paystack charges {{ rands($verifyAmount) }} and refunds it straight away. Your card details are held by Paystack, never by us.</p>
            @endif
            <p>When a job ends, the agreed price plus any extra time is charged to your default card, and a receipt is emailed to you. On high-value bookings we may place a temporary hold for the no-show fee, released when the job ends.</p>
            @if ($eft)<p class="text-mist">Your account is approved for EFT on a monthly invoice. You can choose this when booking.</p>@endif
            <p><a class="underline" href="{{ route('legal', 'cancellation') }}">Cancellation and no-show policy</a></p>
        </section>
    </div>
</x-account-shell>
