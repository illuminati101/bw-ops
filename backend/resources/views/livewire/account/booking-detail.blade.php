@php
    $lead = collect($d['operators'])->first();
    $s = $d['status'];
    [$headline, $explain] = match ($s) {
        'pending_approval' => ['We are reviewing your request', 'Our team confirms new bookings quickly, usually within the hour. You will get an SMS and email as soon as it is confirmed.'],
        'quote_requested' => ['We are preparing your quote', 'A planner is reviewing your requirements and will send a firm price for you to accept.'],
        'quoted' => ['Your quote is ready', 'Review the price below. Your booking is confirmed the moment you accept.'],
        'confirmed' => ['You are booked', 'We will assign your operator ahead of time and send you their name, vehicle and your arrival PIN.'],
        'searching' => ['Finding your operator', 'We are offering your job to the nearest operators who meet your requirements.'],
        'assigned' => [($lead['first_name'] ?? 'Your operator').' is assigned', 'You will see them on the map once they set off.'],
        'en_route' => [($lead['first_name'] ?? 'Your operator').' is on the way', $d['eta_minutes'] ? 'Arriving in about '.$d['eta_minutes'].' min.' : 'You can follow them on the map.'],
        'arrived' => [($lead['first_name'] ?? 'Your operator').' has arrived', 'Ask for your PIN before you get in.'],
        'in_progress' => ['Under way', 'Our control room is monitoring your trip.'],
        'completed' => ['Completed', 'Thank you for travelling with us. Your receipt has been emailed to you.'],
        'cancelled' => ['Cancelled', $d['payment']['cancellation_fee'] > 0 ? 'A cancellation fee of '.rands($d['payment']['cancellation_fee']).' applied.' : 'No fee applied.'],
        'no_show' => ['Recorded as a no-show', 'Your operator waited but could not find you. If this is wrong, please contact us.'],
        'declined' => ['We could not accept this booking', 'Please contact us and we will try to help.'],
        'expired' => ['No operator was available', 'Nothing has been charged. Try scheduling ahead, or call our control room.'],
        default => [$d['status_label'], ''],
    };
@endphp
<x-account-shell active="account">
    <div @if (! $d['is_closed']) wire:poll.12s.visible @endif
         x-data="{ init() { if (window.Echo) { window.Echo.private('booking.{{ $b->id }}').listen('.booking.updated', () => $wire.$refresh()).listen('.operator.location', () => $wire.$refresh()).listen('.chat.message', () => $wire.$refresh()); } }, destroy() { window.Echo?.leave('booking.{{ $b->id }}'); } }">
        <a href="{{ route('account') }}" class="inline-flex items-center gap-2 text-sm text-ash hover:text-bone"><x-glyph name="chevron-left" class="size-4" />All bookings</a>

        <header class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-ash">{{ $d['service']['name'] }}, ref {{ $d['reference'] }}</p>
                <h1 class="mt-1 text-display-m">{{ $headline }}</h1>
                @if ($explain)<p class="mt-2 max-w-2xl text-mist">{{ $explain }}</p>@endif
            </div>
            <span @class(['tag', 'tag-gold' => ! $d['is_closed'] && $s !== 'pending_approval', 'tag-ok' => $s === 'completed', 'tag-alert' => $s === 'no_show'])>{{ $d['status_label'] }}</span>
        </header>

        @if ($flash)
            <div class="mt-6 flex items-start justify-between gap-4 rounded-lg border border-champagne/40 bg-champagne/[.06] px-4 py-3 text-sm" role="status">
                <span>{{ $flash }}</span><button type="button" wire:click="$set('flash', null)" class="text-ash hover:text-bone" aria-label="Dismiss"><x-glyph name="x" class="size-4" /></button>
            </div>
        @endif

        {{-- SOS raised --}}
        @if ($sosId)
            <div class="mt-6 rounded-xl border border-red-500/50 bg-red-950/40 p-6" role="alert">
                <p class="font-display text-3xl">Our control room has been alerted</p>
                <p class="mt-2 text-mist">They can see your booking and your location, and they are responding now. If you are in immediate danger, also call <a class="underline" href="tel:10111">10111</a>.</p>
                <form wire:submit="cancelSos" class="mt-5 flex flex-wrap items-end gap-3">
                    @if ($pinSet)
                        <div><label class="label" for="sospin">Safety PIN to cancel</label><input id="sospin" wire:model="sosPin" inputmode="numeric" maxlength="4" autocomplete="off" class="field w-36 tracking-[.4em]">@error('sosPin')<p class="error">{{ $message }}</p>@enderror</div>
                    @endif
                    <button class="btn btn-line">It was a false alarm</button>
                </form>
            </div>
        @endif

        <div class="mt-8 grid grid-cols-[minmax(0,1fr)] gap-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-8">
                {{-- Quote to accept --}}
                @if ($d['can']['accept_quote'])
                    <section class="panel p-6">
                        <p class="text-sm text-ash">Your quote</p>
                        <p class="mt-1 font-display text-5xl text-champagne-light">{{ rands($d['quote']['total'], false) }}</p>
                        @if ($d['quote']['custom_note'])<p class="mt-3 whitespace-pre-line text-mist">{{ $d['quote']['custom_note'] }}</p>@endif
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button type="button" wire:click="acceptQuote" class="btn btn-gold">Accept and confirm</button>
                            <a href="{{ route('page', 'contact') }}" class="btn btn-line">Ask a question</a>
                        </div>
                    </section>
                @endif

                {{-- Reconfirm --}}
                @if ($d['reconfirm']['required'])
                    <section class="flex flex-col gap-4 rounded-xl border border-champagne/40 bg-champagne/[.06] p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-semibold">Please reconfirm your booking</p><p class="text-sm text-ash">Let us know you still need us{{ $d['reconfirm']['due_at'] ? ' by '.\Carbon\Carbon::parse($d['reconfirm']['due_at'])->format('D j M, H:i') : '' }}. Unconfirmed bookings may be released.</p></div>
                        <button type="button" wire:click="reconfirm" class="btn btn-gold shrink-0">Yes, I still need this</button>
                    </section>
                @endif

                {{-- Map --}}
                @if ($live || $d['pickup']['lat'])
                    <div class="relative overflow-hidden rounded-xl border border-bone/10" wire:ignore
                         x-data="{ m: null, init() { this.$nextTick(() => { this.m = bwTrackMap(this.$refs.map, $wire.mapData); $wire.$watch('mapData', v => this.m.update(v)); }); } }">
                        <div x-ref="map" class="map-noir h-72 md:h-96" role="img" aria-label="Map showing your pickup point{{ $live ? ' and operator' : '' }}"></div>
                    </div>
                @endif

                {{-- Searching --}}
                @if ($s === 'searching')
                    <section class="flex items-center gap-4 rounded-xl border border-bone/10 p-5">
                        <span class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-champagne opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-3 rounded-full bg-champagne"></span></span>
                        <p class="text-mist">Contacting operators near you. This page updates by itself.</p>
                    </section>
                @endif

                {{-- Operators + PIN --}}
                @if (count($d['operators']))
                    <section class="panel divide-y divide-bone/10">
                        @foreach ($d['operators'] as $o)
                            <div class="flex flex-wrap items-center gap-4 p-5" wire:key="op-{{ $o['assignment_id'] }}">
                                @if ($o['photo_url'])
                                    <img src="{{ $o['photo_url'] }}" alt="" class="size-14 shrink-0 rounded-full object-cover">
                                @else
                                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-graphite font-display text-2xl text-champagne-light">{{ mb_substr($o['first_name'], 0, 1) }}</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $o['first_name'] }} <span class="font-normal text-ash">{{ $o['role_label'] }}</span></p>
                                    @if ($o['vehicle'])<p class="text-sm text-mist">{{ $o['vehicle']['label'] }}</p>@endif
                                    <p class="text-sm text-ash">{{ $o['status_label'] }}{{ $o['call_sign'] ? ', call sign '.$o['call_sign'] : '' }}</p>
                                </div>
                                @if ($o['vehicle'])<span class="shrink-0 rounded-md border border-bone/25 bg-night px-3 py-1.5 text-sm font-semibold tracking-wider" title="Registration">{{ $o['vehicle']['registration'] }}</span>@endif
                            </div>
                        @endforeach
                        @if ($d['arrival_pin'])
                            <div class="grid gap-4 p-5 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8">
                                <div>
                                    <p class="text-sm text-mist">Your arrival PIN</p>
                                    <p class="font-display text-6xl leading-none tracking-[.18em] text-champagne-light">{{ $d['arrival_pin'] }}</p>
                                </div>
                                <p class="text-sm text-ash">{{ $d['pin_verified'] ? 'Confirmed by your operator.' : 'Your operator will say this PIN when they meet you. Only get in once they have, and never read it out first.' }}</p>
                            </div>
                        @endif
                        @if ($d['can']['chat'] || $d['can']['call'])
                            <div class="flex flex-wrap gap-2 p-4">
                                @if ($d['can']['chat'])<a href="#chat" class="btn btn-line"><x-glyph name="message-square" class="size-4" />Message</a>@endif
                                @if ($d['can']['call'])<button type="button" wire:click="call" class="btn btn-line"><x-glyph name="phone" class="size-4" />Call privately</button>@endif
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Chat --}}
                @if ($d['can']['chat'] || $messages->isNotEmpty())
                    <section id="chat" class="panel overflow-hidden">
                        <h2 class="border-b hairline px-5 py-4 font-sans text-base font-semibold">Messages</h2>
                        <div class="max-h-80 space-y-3 overflow-y-auto px-5 py-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$el.scrollTop = $el.scrollHeight">
                            @forelse ($messages as $m)
                                <div @class(['flex', 'justify-end' => $m->sender_role === 'client']) wire:key="m-{{ $m->id }}">
                                    <div @class(['max-w-[80%] rounded-2xl px-4 py-2.5 text-sm', 'bg-champagne text-night' => $m->sender_role === 'client', 'bg-graphite' => $m->sender_role !== 'client'])>
                                        @if ($m->sender_role !== 'client')<p class="mb-0.5 text-xs text-ash">{{ $m->sender_role === 'staff' ? 'Control room' : 'Operator' }}</p>@endif
                                        <p class="whitespace-pre-line">{{ $m->body }}</p>
                                        <p @class(['mt-1 text-[11px]', 'text-night/60' => $m->sender_role === 'client', 'text-ash' => $m->sender_role !== 'client'])>{{ $m->created_at->format('H:i') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-ash">Messages go to your operator and our control room. Your phone number stays private.</p>
                            @endforelse
                        </div>
                        @if ($d['can']['chat'])
                            <form wire:submit="sendMessage" class="flex gap-2 border-t hairline p-3">
                                <label for="chatbody" class="sr-only">Message</label>
                                <input id="chatbody" wire:model="chatBody" class="field" placeholder="Write a message" autocomplete="off" maxlength="1000">
                                <button class="btn btn-gold shrink-0">Send</button>
                            </form>
                        @endif
                    </section>
                @endif

                {{-- Rate --}}
                @if ($d['can']['rate'])
                    <section class="panel p-6" x-data="{ hover: 0 }">
                        <h2 class="font-sans text-base font-semibold">How was your {{ $lead ? 'trip with '.$lead['first_name'] : 'experience' }}?</h2>
                        <form wire:submit="rate" class="mt-4 space-y-4">
                            <div class="flex gap-1" role="radiogroup" aria-label="Rating">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" role="radio" aria-checked="{{ $score === $i ? 'true' : 'false' }}" aria-label="{{ $i }} out of 5" wire:click="$set('score', {{ $i }})" x-on:mouseenter="hover = {{ $i }}" x-on:mouseleave="hover = 0"
                                            :class="(hover || {{ $score }}) >= {{ $i }} ? 'text-champagne' : 'text-bone/20'" class="transition-colors"><x-glyph name="star" class="size-8 fill-current" /></button>
                                @endfor
                            </div>
                            @error('score')<p class="error">Choose a rating from 1 to 5.</p>@enderror
                            <textarea wire:model="comment" rows="3" class="field" placeholder="Anything we should know? (optional)"></textarea>
                            <button class="btn btn-gold">Send feedback</button>
                        </form>
                    </section>
                @endif

                {{-- Timeline --}}
                @if (count($d['timeline']))
                    <section>
                        <h2 class="mb-4 font-sans text-base font-semibold">Timeline</h2>
                        <ol class="relative space-y-4 border-l border-bone/10 pl-6">
                            @foreach ($d['timeline'] as $e)
                                <li class="relative"><span class="absolute -left-[1.84rem] top-1.5 size-2.5 rounded-full bg-champagne"></span><p class="text-sm">{{ $e['summary'] }}</p><p class="text-xs text-ash">{{ \Carbon\Carbon::parse($e['at'])->format('D j M, H:i') }}</p></li>
                            @endforeach
                        </ol>
                    </section>
                @endif
            </div>

            {{-- Side --}}
            <aside class="space-y-6">
                <section class="panel divide-y divide-bone/10">
                    <div class="p-5">
                        <p class="text-sm text-ash">When</p>
                        @if ($d['structure'] !== 'single' && count($d['shifts']) > 1)
                            <p class="mt-1 font-semibold">{{ count($d['shifts']) }} shifts</p>
                            <p class="text-sm text-mist">{{ \Carbon\Carbon::parse($d['shifts'][0]['starts_at'])->format('D j M') }} to {{ \Carbon\Carbon::parse(end($d['shifts'])['ends_at'])->format('D j M Y') }}</p>
                        @else
                            <p class="mt-1 font-semibold">{{ $b->isOnDemand() ? 'On demand, '.$b->starts_at->format('j M H:i') : $b->starts_at->format('l j F, H:i') }}</p>
                            <p class="text-sm text-mist">Until about {{ $b->plannedEnd()->format('H:i') }} ({{ app(\App\Services\Booking\BookingService::class)->humanMinutes((int) $d['duration_minutes']) }})</p>
                        @endif
                    </div>
                    <div class="space-y-3 p-5 text-sm">
                        <div><p class="text-ash">{{ $d['service']['includes_transport'] ? 'Pickup' : 'Meeting point' }}</p><p>{{ $d['pickup']['address'] }}</p></div>
                        @foreach ($d['stops'] as $i => $st)<div><p class="text-ash">Stop {{ $i + 1 }}</p><p>{{ $st['address'] }}</p></div>@endforeach
                        @if ($d['dropoff'])<div><p class="text-ash">Drop-off</p><p>{{ $d['dropoff']['address'] }}</p></div>@endif
                    </div>
                    @if ($d['principal'] || count($d['requirements']) || $d['notes'] || $d['guards'])
                        <div class="space-y-3 p-5 text-sm">
                            @if ($d['principal'])<div><p class="text-ash">For</p><p>{{ $d['principal']['name'] }}</p></div>@endif
                            @if ($d['guards'])<div><p class="text-ash">Team</p><p>{{ $d['guards'] }} protection officer{{ $d['guards'] > 1 ? 's' : '' }}{{ $d['service']['includes_transport'] ? ', '.$d['vehicles'].' vehicle'.($d['vehicles'] > 1 ? 's' : '') : '' }}</p></div>@endif
                            @if (count($d['requirements']))<div><p class="text-ash">Requirements</p><p>{{ collect($d['requirements'])->implode(', ') }}</p></div>@endif
                            @if ($d['notes'])<div><p class="text-ash">Notes</p><p class="whitespace-pre-line">{{ $d['notes'] }}</p></div>@endif
                        </div>
                    @endif
                    <div class="p-5">
                        @if ($d['quote']['awaiting_quote'])
                            <p class="text-sm text-ash">Price</p><p class="mt-1">Quote on its way</p>
                        @else
                            <div class="flex items-baseline justify-between"><p class="text-sm text-ash">{{ $d['quote']['final_total'] ? 'Total charged' : 'Quoted price' }}</p><p class="font-display text-3xl">{{ rands($d['quote']['final_total'] ?? $d['quote']['total'], false) }}</p></div>
                            <details class="mt-3 text-sm">
                                <summary class="cursor-pointer text-ash hover:text-bone">Price breakdown</summary>
                                <dl class="mt-3 space-y-1.5">
                                    @foreach ($d['quote']['lines'] as $l)<div class="flex justify-between gap-3"><dt class="text-ash">{{ $l['label'] }}</dt><dd>{{ rands($l['amount']) }}</dd></div>@endforeach
                                    @if ($d['quote']['surcharge'])<div class="flex justify-between gap-3"><dt class="text-ash">{{ $d['quote']['surcharge']['label'] }}</dt><dd>{{ rands($d['quote']['surcharge']['amount']) }}</dd></div>@endif
                                </dl>
                                @if ($d['quote']['overtime_rate'])<p class="mt-3 text-xs text-ash">Extra time: {{ rands($d['quote']['overtime_rate'], false) }} per hour.</p>@endif
                            </details>
                            <p class="mt-3 text-sm text-mist">{{ match ($d['payment']['method']) { 'eft' => 'Monthly invoice (EFT)', 'card_machine' => 'Card machine at the end', default => $d['payment']['card'] ?? 'Card on file' } }}</p>
                        @endif
                    </div>
                </section>

                @if ($d['can']['sos'] && ! $sosId)
                    <div x-data="{ confirm: false, busy: false,
                            send() { this.busy = true; const go = (lat, lng) => $wire.raiseSos(lat, lng).then(() => { this.busy = false; this.confirm = false; this.track(); });
                                if (!navigator.geolocation) return go(null, null);
                                navigator.geolocation.getCurrentPosition(p => go(p.coords.latitude, p.coords.longitude), () => go(null, null), { enableHighAccuracy: true, timeout: 5000 }); },
                            track() { if (navigator.geolocation) navigator.geolocation.watchPosition(p => $wire.updateSosLocation(p.coords.latitude, p.coords.longitude), null, { enableHighAccuracy: true, maximumAge: 15000 }); } }">
                        <button type="button" x-show="!confirm" x-on:click="confirm = true" class="flex w-full items-center justify-center gap-3 rounded-xl border border-red-500/60 bg-red-950/30 px-5 py-4 font-semibold text-red-200 hover:bg-red-900/40">
                            <x-glyph name="siren" class="size-5" />SOS: alert the control room
                        </button>
                        <div x-cloak x-show="confirm" class="rounded-xl border border-red-500/60 bg-red-950/40 p-5">
                            <p class="font-semibold">Send a silent alert?</p>
                            <p class="mt-1 text-sm text-mist">Our control room sees your location and responds immediately. Your operator is not told on screen.</p>
                            <div class="mt-4 flex gap-2">
                                <button type="button" x-on:click="send()" :disabled="busy" class="btn grow bg-red-600 text-white hover:bg-red-500"><span x-text="busy ? 'Sending…' : 'Send alert'"></span></button>
                                <button type="button" x-on:click="confirm = false" class="btn btn-ghost">Back</button>
                            </div>
                        </div>
                    </div>
                @endif

                <section class="space-y-2">
                    @if ($d['can']['share'])
                        @if ($shareUrl)
                            <div class="rounded-xl border border-bone/10 p-4" x-data="{ copied: false }">
                                <p class="text-sm font-semibold">Trip link ready</p>
                                <p class="mt-1 break-all text-sm text-ash">{{ $shareUrl }}</p>
                                <div class="mt-3 flex gap-2">
                                    <button type="button" class="btn btn-line grow" x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)"><span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
                                    <a class="btn btn-line grow" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode('Follow my trip with '.settings('company.trading_name').': '.$shareUrl) }}">WhatsApp</a>
                                </div>
                                <p class="mt-2 text-xs text-ash">Anyone with this link can follow the trip until it ends.</p>
                            </div>
                        @else
                            <button type="button" wire:click="share" class="btn btn-line w-full"><x-glyph name="share-2" class="size-4" />Share trip with someone</button>
                        @endif
                    @endif
                    @if ($controlRoom)<a href="tel:{{ preg_replace('/\s+/', '', $controlRoom) }}" class="btn btn-ghost w-full"><x-glyph name="headset" class="size-4" />Call the control room</a>@endif
                    @if ($cancelFee)
                        <div x-data="{ open: false }" x-on:close-dialog.window="open = false">
                            <button type="button" x-on:click="open = true" class="btn btn-ghost w-full text-ash">Cancel booking</button>
                            <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4" x-on:keydown.escape.window="open = false">
                                <div class="panel w-full max-w-md p-6" role="dialog" aria-modal="true" aria-labelledby="cancel-title" x-on:click.outside="open = false">
                                    <h2 id="cancel-title" class="font-display text-3xl">Cancel this booking?</h2>
                                    <p class="mt-3 text-mist">{{ $cancelFee['reason'] }}</p>
                                    <p class="mt-4 flex items-baseline justify-between border-y hairline py-3"><span class="text-ash">Cancellation fee</span><span class="font-display text-3xl {{ $cancelFee['fee'] > 0 ? 'text-champagne-light' : '' }}">{{ $cancelFee['fee'] > 0 ? rands($cancelFee['fee']) : 'None' }}</span></p>
                                    <label class="label mt-4" for="creason">Reason (optional)</label>
                                    <textarea id="creason" wire:model="cancelReason" rows="2" class="field"></textarea>
                                    <div class="mt-5 flex gap-2">
                                        <button type="button" wire:click="cancel" class="btn btn-gold grow">Cancel booking</button>
                                        <button type="button" x-on:click="open = false" class="btn btn-line grow">Keep it</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-account-shell>
