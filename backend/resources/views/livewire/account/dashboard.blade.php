<x-account-shell active="account" :heading="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening')).', '.$user->firstName().'.'">
    <div wire:poll.15s class="space-y-8">
        @if (feature_enabled('id_verification') && $verification !== 'verified')
            <div class="flex flex-col gap-4 rounded-xl border border-champagne/40 bg-champagne/[.06] p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold">{{ $verification === 'pending' ? 'We are reviewing your ID' : 'Verify your identity before your first booking' }}</p>
                    <p class="text-sm text-ash">{{ $verification === 'pending' ? 'This usually takes less than an hour during business hours. You can still request bookings.' : 'A photo of your ID or passport and a selfie. It takes two minutes, and protects our operators.' }}</p>
                </div>
                @if ($verification !== 'pending')<a href="{{ route('account.verify') }}" class="btn btn-gold shrink-0">Verify now</a>@endif
            </div>
        @endif
        @unless ($hasCard)
            <div class="flex flex-col gap-4 rounded-xl border border-bone/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="font-semibold">Add a card</p><p class="text-sm text-ash">Needed to book. Nothing is charged until a job is complete.</p></div>
                <a href="{{ route('account.payment') }}" class="btn btn-line shrink-0">Add card</a>
            </div>
        @endunless

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="seg" role="tablist">
                <button type="button" wire:click="$set('tab', 'upcoming')" aria-pressed="{{ $tab === 'upcoming' ? 'true' : 'false' }}">Upcoming ({{ $upcoming->count() }})</button>
                <button type="button" wire:click="$set('tab', 'past')" aria-pressed="{{ $tab === 'past' ? 'true' : 'false' }}">Past</button>
            </div>
            <div class="flex gap-2">
                @if (feature_enabled('on_demand'))<a href="{{ route('book', ['mode' => 'on_demand']) }}" class="btn btn-line">Hail now</a>@endif
                <a href="{{ route('book') }}" class="btn btn-gold">New booking</a>
            </div>
        </div>

        @php($list = $tab === 'past' ? $past : $upcoming)
        @forelse ($list as $b)
            <a href="{{ route('account.booking', $b->reference) }}" wire:key="b-{{ $b->id }}" class="group grid gap-4 rounded-xl border border-bone/10 p-5 transition-colors hover:border-bone/25 md:grid-cols-[9rem_1fr_auto] md:items-center">
                <div>
                    <p class="font-display text-3xl leading-none">{{ $b->starts_at->format('j M') }}</p>
                    <p class="mt-1 text-sm text-ash">{{ $b->isOnDemand() ? 'On demand' : $b->starts_at->format('D, H:i') }}</p>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold">{{ $b->service->name }} @if($b->principal_name)<span class="font-normal text-ash">for {{ $b->principal_name }}</span>@endif</p>
                    <p class="truncate text-sm text-ash">{{ $b->pickup_address }}</p>
                    @if ($b->activeAssignments->isNotEmpty())<p class="mt-1 text-sm text-mist">{{ $b->activeAssignments->map(fn ($a) => $a->operator->displayName())->implode(', ') }}</p>@endif
                </div>
                <div class="flex items-center gap-3 md:flex-col md:items-end">
                    <span @class(['tag', 'tag-gold' => in_array($b->status->value, ['assigned', 'en_route', 'arrived', 'in_progress', 'confirmed']), 'tag-ok' => $b->status->value === 'completed', 'tag-alert' => in_array($b->status->value, ['no_show'])])>{{ $b->status->getLabel() }}</span>
                    <span class="text-sm text-mist">{{ $b->status->value === 'quote_requested' ? 'Quote pending' : rands($b->final_total ?? $b->quoted_total, false) }}</span>
                    @if ($b->reconfirm_due_at && ! $b->reconfirmed_at && ! $b->status->isClosed())<span class="tag tag-alert">Please reconfirm</span>@endif
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-bone/15 p-10 text-center">
                <p class="font-display text-3xl">{{ $tab === 'past' ? 'No past bookings yet' : 'Nothing booked yet' }}</p>
                <p class="mt-2 text-ash">Book ahead, or hail a protected ride in minutes.</p>
                <a href="{{ route('book') }}" class="btn btn-gold mt-6">Make a booking</a>
            </div>
        @endforelse
    </div>
</x-account-shell>
