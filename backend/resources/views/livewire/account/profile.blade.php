<x-account-shell active="account.profile" heading="Profile and safety">
    <div class="max-w-3xl space-y-14">
        <section aria-labelledby="h-details">
            <h2 id="h-details" class="font-sans text-lg font-semibold">Your details</h2>
            <form wire:submit="saveDetails" class="mt-5 space-y-5">
                <div class="flex items-center gap-5">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-full object-cover">
                    @elseif ($user->avatar_path)
                        <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="" class="size-16 rounded-full object-cover">
                    @else
                        <span class="grid size-16 place-items-center rounded-full bg-graphite font-display text-3xl text-champagne-light">{{ mb_substr($user->name, 0, 1) }}</span>
                    @endif
                    <label class="btn btn-line cursor-pointer"><x-glyph name="camera" class="size-4" />{{ $user->avatar_path ? 'Change photo' : 'Add a photo' }}<input type="file" wire:model="photo" accept="image/*" class="sr-only"></label>
                    <p class="hidden text-sm text-ash sm:block">Helps your operator recognise you.</p>
                </div>
                @error('photo')<p class="error">{{ $message }}</p>@enderror
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label class="label" for="name">Full name</label><input id="name" wire:model="name" class="field" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="email">Email</label><input id="email" type="email" wire:model="email" class="field" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror<p class="hint">Receipts and booking confirmations go here.</p></div>
                    <div><label class="label">Mobile number</label><input value="{{ \App\Support\PhoneNumber::display($user->phone) }}" class="field opacity-60" disabled><p class="hint">To change your number, contact us.</p></div>
                    <div><label class="label" for="code">Code name (optional)</label><input id="code" wire:model="codeName" class="field" placeholder="e.g. Falcon">@error('codeName')<p class="error">{{ $message }}</p>@enderror<p class="hint">Operators see this instead of your name on their device.</p></div>
                </div>
                <label class="flex items-center gap-3 text-sm"><input type="checkbox" wire:model="marketing" class="accent-champagne">Send me occasional news and offers</label>
                <div class="flex items-center gap-4"><button class="btn btn-gold">Save details</button>@if ($saved === 'details')<span class="text-sm text-champagne-light" x-data x-init="setTimeout(() => $wire.set('saved', null), 3000)">Saved</span>@endif</div>
            </form>
        </section>

        @if (feature_enabled('sos'))
        <section aria-labelledby="h-pins" class="border-t hairline pt-12">
            <h2 id="h-pins" class="font-sans text-lg font-semibold">Safety PIN</h2>
            <p class="mt-2 max-w-2xl text-ash">Your safety PIN cancels an SOS alert if you raise one by mistake. If you are ever forced to cancel, enter your duress PIN instead: it looks exactly like a normal cancel on screen, but our control room treats it as a live emergency.</p>
            <p class="mt-4 text-sm">{{ $pinSet ? 'Safety PIN is set.' : 'No safety PIN yet.' }} {{ $duressSet ? 'Duress PIN is set.' : '' }}</p>
            <form wire:submit="savePins" class="mt-5 grid gap-5 sm:grid-cols-[10rem_10rem_auto] sm:items-end">
                <div><label class="label" for="spin">{{ $pinSet ? 'New safety PIN' : 'Safety PIN' }}</label><input id="spin" wire:model="safetyPin" type="password" inputmode="numeric" maxlength="4" autocomplete="new-password" class="field tracking-[.4em]"></div>
                <div><label class="label" for="dpin">Duress PIN</label><input id="dpin" wire:model="duressPin" type="password" inputmode="numeric" maxlength="4" autocomplete="new-password" class="field tracking-[.4em]"></div>
                <div class="flex items-center gap-4"><button class="btn btn-line">Save PINs</button>@if ($saved === 'pins')<span class="text-sm text-champagne-light">Saved</span>@endif</div>
                <div class="sm:col-span-3">@error('safetyPin')<p class="error">{{ $message }}</p>@enderror @error('duressPin')<p class="error">{{ $message }}</p>@enderror<p class="hint">Four digits each. Choose numbers you will remember under stress, and don't use your bank PIN.</p></div>
            </form>
        </section>
        @endif

        <section aria-labelledby="h-contacts" class="border-t hairline pt-12">
            <h2 id="h-contacts" class="font-sans text-lg font-semibold">Emergency contacts</h2>
            <p class="mt-2 text-ash">People our control room may contact if you raise an SOS. Up to five.</p>
            <ul class="mt-5 divide-y divide-bone/10 rounded-xl border border-bone/10">
                @forelse ($contacts as $c)
                    <li class="flex items-center justify-between gap-4 px-4 py-3" wire:key="c-{{ $c->id }}">
                        <div><p class="font-medium">{{ $c->name }} @if($c->relationship)<span class="text-ash">({{ $c->relationship }})</span>@endif</p><p class="text-sm text-ash">{{ \App\Support\PhoneNumber::display($c->phone) }}{{ $c->notify_on_sos ? ', notified by SMS on SOS' : '' }}</p></div>
                        <button type="button" wire:click="removeContact({{ $c->id }})" wire:confirm="Remove {{ $c->name }}?" class="btn btn-ghost !px-3" aria-label="Remove {{ $c->name }}"><x-glyph name="trash-2" class="size-4" /></button>
                    </li>
                @empty
                    <li class="px-4 py-5 text-sm text-ash">No emergency contacts yet.</li>
                @endforelse
            </ul>
            @if ($contacts->count() < 5)
                <form wire:submit="addContact" class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div><label class="label" for="cn">Name</label><input id="cn" wire:model="contactName" class="field">@error('contactName')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="cp">Mobile number</label><input id="cp" wire:model="contactPhone" type="tel" class="field">@error('contactPhone')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="cr">Relationship</label><input id="cr" wire:model="contactRelationship" class="field" placeholder="e.g. Spouse"></div>
                    <label class="flex items-center gap-3 text-sm sm:col-span-2"><input type="checkbox" wire:model="contactNotify" class="accent-champagne">Send them an SMS if I raise an SOS</label>
                    <div class="sm:text-right"><button class="btn btn-line">Add contact</button></div>
                </form>
            @endif
        </section>

        <section aria-labelledby="h-delete" class="border-t hairline pt-12" x-data="{ open: false }">
            <h2 id="h-delete" class="font-sans text-lg font-semibold">Delete your account</h2>
            <p class="mt-2 max-w-2xl text-ash">This removes your profile, saved cards, emergency contacts and ID documents. We keep anonymised booking and payment records for as long as tax law requires. See our <a class="underline" href="{{ route('legal', 'privacy') }}">privacy policy</a>.</p>
            <button type="button" x-show="!open" x-on:click="open = true" class="btn btn-ghost mt-4 text-red-300">Delete my account</button>
            <form x-cloak x-show="open" wire:submit="deleteAccount" class="mt-5 flex flex-wrap items-end gap-3">
                <div><label class="label" for="del">Type DELETE to confirm</label><input id="del" wire:model="deleteConfirm" class="field w-48" autocomplete="off">@error('deleteConfirm')<p class="error">{{ $message }}</p>@enderror</div>
                <button class="btn bg-red-700 text-white hover:bg-red-600">Delete permanently</button>
                <button type="button" x-on:click="open = false" class="btn btn-ghost">Keep my account</button>
            </form>
        </section>
    </div>
</x-account-shell>
