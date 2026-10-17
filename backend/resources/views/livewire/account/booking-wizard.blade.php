<div class="wrap py-10 md:py-14">
    <header class="mb-10 max-w-3xl">
        <h1 class="text-display-l">{{ $mode === 'on_demand' ? 'Hail a protected ride' : 'Make a booking' }}</h1>
        <p class="mt-3 text-ash">Your price updates as you go. Nothing is charged until the job is done.</p>
    </header>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-12">
            {{-- Service --}}
            <section aria-labelledby="s-service">
                <h2 id="s-service" class="mb-4 font-sans text-lg font-semibold">Service</h2>
                <div class="grid gap-2">
                    @foreach ($services as $s)
                        <label wire:key="svc-{{ $s->id }}" @class(['flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition-colors', 'border-champagne/60 bg-champagne/[.05]' => $serviceId === $s->id, 'border-bone/10 hover:border-bone/25' => $serviceId !== $s->id])>
                            <input type="radio" wire:model.live="serviceId" value="{{ $s->id }}" class="mt-1.5 accent-champagne">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline justify-between gap-x-4">
                                    <span class="font-display text-2xl">{{ $s->name }}</span>
                                    <span class="text-sm text-ash">@if($s->custom_quote) Tailored quote @elseif($c = $s->rateCardFor($region)) From {{ rands(max($c->minimum_charge, $c->callout_fee + $c->per_hour * $s->min_duration_minutes / 60), false) }} @endif</span>
                                </span>
                                <span class="mt-1 block text-sm text-ash">{{ $s->summary }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            @if ($service)
            {{-- When --}}
            <section aria-labelledby="s-when">
                <h2 id="s-when" class="mb-4 font-sans text-lg font-semibold">When</h2>
                @php($canNow = $service->allows_on_demand && feature_enabled('on_demand'))
                @php($canLater = $service->allows_advance && feature_enabled('advance'))
                @if ($canNow && $canLater && ! $isMulti)
                    <div class="seg mb-5" role="group" aria-label="When">
                        <button type="button" wire:click="$set('mode', 'on_demand')" aria-pressed="{{ $mode === 'on_demand' ? 'true' : 'false' }}">As soon as possible</button>
                        <button type="button" wire:click="$set('mode', 'advance')" aria-pressed="{{ $mode === 'advance' ? 'true' : 'false' }}">Schedule</button>
                    </div>
                @endif
                @if ($mode === 'on_demand')
                    <p class="text-ash">We'll send the nearest available operator who meets your requirements. You'll see them on the map as soon as they accept.</p>
                @elseif ($isMulti)
                    <div class="grid gap-4 sm:grid-cols-2">
                        @if (feature_enabled('recurring'))
                            <div class="sm:col-span-2 seg w-fit">
                                <button type="button" wire:click="$set('schedule.type', 'multi_day')" aria-pressed="{{ $schedule['type'] === 'multi_day' ? 'true' : 'false' }}">Every day</button>
                                <button type="button" wire:click="$set('schedule.type', 'recurring')" aria-pressed="{{ $schedule['type'] === 'recurring' ? 'true' : 'false' }}">Selected weekdays</button>
                            </div>
                        @endif
                        <div><label class="label">First day</label><input type="date" wire:model.live.debounce.500ms="schedule.start_date" min="{{ $minDate }}" max="{{ $maxDate }}" class="field"></div>
                        <div><label class="label">Last day</label><input type="date" wire:model.live.debounce.500ms="schedule.end_date" min="{{ $minDate }}" max="{{ $maxDate }}" class="field"></div>
                        <div><label class="label">Daily start</label><input type="time" wire:model.live.debounce.500ms="schedule.start_time" class="field"></div>
                        <div><label class="label">Daily finish</label><input type="time" wire:model.live.debounce.500ms="schedule.end_time" class="field"></div>
                        @if ($schedule['type'] === 'recurring')
                            <div class="sm:col-span-2 flex flex-wrap gap-2">
                                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $d => $label)
                                    <label class="tag cursor-pointer has-[:checked]:border-champagne has-[:checked]:text-champagne-light"><input type="checkbox" wire:model.live="schedule.weekdays" value="{{ $d }}" class="sr-only">{{ $label }}</label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div><label class="label" for="w-date">Date</label><input id="w-date" type="date" wire:model.live.debounce.500ms="date" min="{{ $minDate }}" max="{{ $maxDate }}" class="field"></div>
                        <div><label class="label" for="w-time">Pickup time</label><input id="w-time" type="time" step="900" wire:model.live.debounce.500ms="time" class="field"></div>
                        <div><label class="label" for="w-dur">For</label>
                            <select id="w-dur" wire:model.live="duration" class="field">
                                @foreach ([60, 90, 120, 150, 180, 240, 300, 360, 480, 600, 720] as $m)
                                    @if ($m >= ($quote['minimum_minutes'] ?? $service->min_duration_minutes) || $m === $duration)
                                        <option value="{{ $m }}">{{ intdiv($m, 60) }} hour{{ $m >= 120 ? 's' : '' }}{{ $m % 60 ? ' 30 min' : '' }}</option>
                                    @endif
                                @endforeach
                            </select></div>
                    </div>
                    <p class="hint">At least {{ app(\App\Services\Booking\BookingService::class)->humanMinutes(max($service->min_notice_minutes, (int) settings($service->structure === 'single' ? 'booking.notice_minutes' : 'booking.notice_minutes_team'))) }} notice for this service.</p>
                @endif
            </section>

            {{-- Where --}}
            <section aria-labelledby="s-where" class="space-y-4">
                <h2 id="s-where" class="font-sans text-lg font-semibold">{{ $service->includes_transport ? 'Where' : 'Where should your officer meet you?' }}</h2>
                <x-place-input field="pickup" :value="$pickup" :label="$service->includes_transport ? 'Pickup' : 'Meeting point'" :locate="true" wire:key="pickup-{{ $pickup['lat'] ?? 'x' }}" />
                @if ($service->includes_transport)
                    @foreach ($stops as $i => $stop)
                        <div class="flex items-end gap-3" wire:key="stop-{{ $i }}">
                            <div class="flex-1"><x-place-input field="stop" :index="$i" :value="$stop['lat'] ? $stop : null" :label="'Stop '.($i + 1)" /></div>
                            <div class="w-28"><label class="label">Wait</label><select wire:model.live="stops.{{ $i }}.wait_minutes" class="field">@foreach ([0, 15, 30, 45, 60, 90, 120, 180] as $w)<option value="{{ $w }}">{{ $w }} min</option>@endforeach</select></div>
                            <button type="button" wire:click="removeStop({{ $i }})" class="btn btn-ghost" aria-label="Remove stop"><x-glyph name="x" class="size-4" /></button>
                        </div>
                    @endforeach
                    @if (feature_enabled('multi_stop') && count($stops) < 8)<button type="button" wire:click="addStop" class="text-sm text-champagne hover:text-champagne-light"><x-glyph name="plus" class="inline size-4" /> Add a stop</button>@endif
                    <x-place-input field="dropoff" :value="$dropoff" label="Drop-off (optional for hourly bookings)" :clearable="true" wire:key="drop-{{ $dropoff['lat'] ?? 'x' }}" />
                    @if ($route && $route['km'] > 0)<p class="hint">About {{ $route['km'] }} km, {{ $route['minutes'] }} minutes' driving.</p>@endif
                @endif
            </section>

            {{-- Requirements --}}
            <section aria-labelledby="s-req" class="space-y-6">
                <h2 id="s-req" class="font-sans text-lg font-semibold">Requirements</h2>
                @if (feature_enabled('team_bookings') || ! $service->includes_transport)
                    <div class="flex flex-wrap gap-6">
                        @if ($service->includes_protection || ! $service->includes_transport)
                            <div><p class="label">Protection officers</p>
                                <div class="flex items-center gap-3"><button type="button" class="btn btn-line !min-h-10 !px-3" wire:click="step('guards', -1, {{ $service->includes_transport ? 0 : 1 }}, {{ feature_enabled('team_bookings') ? 20 : max(1, $service->default_guards) }})" aria-label="Fewer officers"><x-glyph name="minus" class="size-4" /></button><span class="w-8 text-center text-xl font-semibold tabular-nums">{{ $guards }}</span><button type="button" class="btn btn-line !min-h-10 !px-3" wire:click="step('guards', 1, 0, {{ feature_enabled('team_bookings') ? 20 : max(1, $service->default_guards) }})" aria-label="More officers"><x-glyph name="plus" class="size-4" /></button></div></div>
                        @endif
                        @if ($service->includes_transport && feature_enabled('team_bookings'))
                            <div><p class="label">Vehicles</p>
                                <div class="flex items-center gap-3"><button type="button" class="btn btn-line !min-h-10 !px-3" wire:click="step('vehicles', -1, 1, 10)" aria-label="Fewer vehicles"><x-glyph name="minus" class="size-4" /></button><span class="w-8 text-center text-xl font-semibold tabular-nums">{{ $vehicles }}</span><button type="button" class="btn btn-line !min-h-10 !px-3" wire:click="step('vehicles', 1, 1, 10)" aria-label="More vehicles"><x-glyph name="plus" class="size-4" /></button></div></div>
                        @endif
                        @if ($service->includes_transport)
                            <div><label class="label" for="pax">Passengers</label><select id="pax" wire:model.live="passengers" class="field w-28">@for ($i = 1; $i <= 7; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
                        @endif
                    </div>
                @endif
                @if ($filters->isNotEmpty())
                    <div>
                        <p class="label">Request specific capabilities</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($filters as $f)
                                @continue($f->applies_to === 'vehicle' && ! $service->includes_transport)
                                <label wire:key="f-{{ $f->id }}" class="tag cursor-pointer select-none py-1.5 has-[:checked]:border-champagne has-[:checked]:bg-champagne/10 has-[:checked]:text-champagne-light">
                                    <input type="checkbox" wire:model.live="featureIds" value="{{ $f->id }}" class="sr-only">{{ $f->labelForClients() }}
                                    @if ($f->surcharge_type !== 'none')<span class="text-ash">+{{ rands($f->surcharge_amount, false) }}{{ $f->surcharge_type === 'per_hour' ? '/h' : '' }}</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($extras->isNotEmpty())
                    <div>
                        <p class="label">Add-ons</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($extras as $e)
                                <label wire:key="e-{{ $e->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-bone/10 p-3 has-[:checked]:border-champagne/50">
                                    <input type="checkbox" wire:model.live="extraIds" value="{{ $e->id }}" class="mt-1 accent-champagne">
                                    <span class="text-sm"><span class="font-semibold">{{ $e->name }}</span> <span class="text-ash">{{ $e->pricing === 'percent' ? $e->amount.'%' : rands($e->amount, false).($e->pricing === 'per_hour' ? '/h' : '') }}</span><span class="block text-ash">{{ $e->description }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($previousOperators->isNotEmpty())
                    <div class="max-w-sm"><label class="label" for="pref">Preferred operator</label>
                        <select id="pref" wire:model.live="preferredOperatorId" class="field"><option value="">Any available operator</option>@foreach ($previousOperators as $op)<option value="{{ $op->id }}">{{ $op->displayName() }} ({{ $op->call_sign }})</option>@endforeach</select>
                        <p class="hint">We assign them whenever they're available.</p></div>
                @endif
            </section>

            {{-- Who --}}
            <section aria-labelledby="s-who" class="space-y-4">
                <h2 id="s-who" class="font-sans text-lg font-semibold">Who</h2>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model.live="forSomeoneElse" class="accent-champagne"> I'm booking for someone else</label>
                @if ($forSomeoneElse)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="pn">Their name</label><input id="pn" wire:model.blur="principalName" class="field">@error('principalName')<p class="error">{{ $message }}</p>@enderror</div>
                        <div><label class="label" for="pp">Their mobile number</label><input id="pp" wire:model.blur="principalPhone" type="tel" class="field">@error('principalPhone')<p class="error">{{ $message }}</p>@enderror<p class="hint">They'll get the operator's details, the PIN and a tracking link by SMS.</p></div>
                    </div>
                @endif
                <div><label class="label" for="notes">Notes for your operator</label><textarea id="notes" wire:model.blur="notes" rows="3" class="field" placeholder="Gate codes, where to wait, luggage, anything that helps."></textarea></div>
            </section>
            @endif
        </div>

        {{-- Summary --}}
        <aside id="summary" class="scroll-mt-6 lg:sticky lg:top-6 lg:h-fit">
            <div class="panel overflow-hidden">
                <div class="border-b hairline p-6">
                    <p class="text-sm text-ash">Your quote</p>
                    <div wire:loading.class="opacity-40" wire:target="refreshQuote,setPlace,useMyLocation,step,serviceId,mode,date,time,duration,featureIds,extraIds,guards,vehicles,schedule,stops,passengers">
                        @if ($quote && ! $quote['custom_quote'])
                            <p class="mt-1 font-display text-5xl text-champagne-light">{{ rands($quote['total'], false) }}</p>
                            <dl class="mt-5 space-y-2 text-sm">
                                @foreach ($quote['lines'] as $l)
                                    <div class="flex justify-between gap-4"><dt class="text-ash">{{ $l['label'] }}</dt><dd class="shrink-0">{{ rands($l['amount']) }}</dd></div>
                                @endforeach
                                @if ($quote['surcharge'])<div class="flex justify-between gap-4"><dt class="text-ash">{{ $quote['surcharge']['label'] }}</dt><dd>{{ rands($quote['surcharge']['amount']) }}</dd></div>@endif
                                @if ($quote['vat_included'] > 0)<div class="flex justify-between gap-4 text-xs"><dt class="text-ash">Includes VAT</dt><dd>{{ rands($quote['vat_included']) }}</dd></div>@endif
                            </dl>
                            <p class="mt-4 text-xs text-ash">Extra time is {{ rands($quote['overtime_rate'], false) }} per hour after a short grace period.</p>
                        @elseif ($quote && $quote['custom_quote'])
                            <p class="mt-1 font-display text-3xl">Tailored quote</p>
                            <p class="mt-3 text-sm text-ash">Our team will review your request and send a firm price to accept, usually within a few hours. Estimate: around {{ rands($quote['total'], false) }}.</p>
                        @else
                            <p class="mt-2 text-sm text-mist">{{ $quoteError ?? 'Choose a service to see your price.' }}</p>
                        @endif
                    </div>
                    @if ($quote && $quoteError)<p class="error mt-4">{{ $quoteError }}</p>@endif
                </div>

                @auth
                <div class="space-y-4 border-b hairline p-6">
                    <p class="text-sm font-semibold">Payment after the job</p>
                    @if ($cards->isEmpty() && $paymentMethod === 'card')
                        <button type="button" wire:click="leaveFor('payment')" class="btn btn-line w-full">Add a card</button>
                    @endif
                    @foreach ($cards as $card)
                        <label class="flex items-start gap-3 text-sm"><input type="radio" name="pm" @checked($paymentMethod === 'card' && $paymentMethodId === $card->id) wire:click="$set('paymentMethod', 'card'); $set('paymentMethodId', {{ $card->id }})" class="mt-1 accent-champagne"><span>{{ $card->label() }}</span></label>
                    @endforeach
                    @if (feature_enabled('card_machine') && $cards->isNotEmpty())
                        <label class="flex items-start gap-3 text-sm"><input type="radio" name="pm" @checked($paymentMethod === 'card_machine') wire:click="$set('paymentMethod', 'card_machine')" class="mt-1 accent-champagne"><span>Card machine at the end<span class="block text-ash">Your saved card still secures the booking.</span></span></label>
                    @endif
                    @if ($eftAllowed)
                        <label class="flex items-start gap-3 text-sm"><input type="radio" name="pm" @checked($paymentMethod === 'eft') wire:click="$set('paymentMethod', 'eft')" class="mt-1 accent-champagne"><span>EFT on monthly invoice</span></label>
                    @endif
                    @if ($companies->isNotEmpty())
                        <div><label class="label" for="co">Bill to</label><select id="co" wire:model.live="companyId" class="field"><option value="">Me personally</option>@foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    @endif
                </div>
                @endauth

                <div class="space-y-4 p-6">
                    @if ($quote)
                        <p class="text-xs text-ash">Free cancellation up to {{ collect(settings('cancellation.tiers'))->sortByDesc('hours')->first()['hours'] ?? 24 }} hours before. No-show fee {{ rands($quote['no_show_fee'], false) }}.@if ($quote['hold_amount'] > 0) We'll hold {{ rands($quote['hold_amount'], false) }} on your card until the job ends.@endif <a class="underline" target="_blank" href="{{ route('legal', 'cancellation') }}">Policy</a></p>
                    @endif
                    @auth
                        @if (feature_enabled('id_verification') && in_array($verification, ['unverified', 'rejected', null], true))
                            <button type="button" wire:click="leaveFor('verify')" class="btn btn-gold w-full">Verify your ID to book</button>
                        @else
                            <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" class="btn btn-gold w-full" @disabled(! $quote)>
                                <span wire:loading.remove wire:target="confirm">{{ ($quote['custom_quote'] ?? false) ? 'Request quote' : ($mode === 'on_demand' ? 'Hail now' : 'Confirm booking') }}</span>
                                <span wire:loading wire:target="confirm">Working…</span>
                            </button>
                        @endif
                    @else
                        <button type="button" wire:click="confirm" class="btn btn-gold w-full" @disabled(! $quote)>Sign in to book</button>
                        <p class="text-center text-xs text-ash">We'll keep your details while you sign in.</p>
                    @endauth
                </div>
            </div>
        </aside>
    </div>

    {{-- Phones: the total stays in view while filling in the form. --}}
    @if ($quote)
        <div class="fixed inset-x-0 bottom-0 z-30 border-t hairline bg-night/95 backdrop-blur lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)">
            <div class="wrap flex items-center justify-between gap-4 py-3">
                <div><p class="text-xs text-ash">{{ $quote['custom_quote'] ? 'Estimate' : 'Total' }}</p><p class="font-display text-3xl leading-none text-champagne-light">{{ rands($quote['total'], false) }}</p></div>
                <a href="#summary" class="btn btn-gold">Review and book</a>
            </div>
        </div>
        <div class="h-20 lg:hidden"></div>
    @endif
</div>
