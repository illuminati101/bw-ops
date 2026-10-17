<x-account-shell active="account.verify" heading="Verify your identity">
    <div class="max-w-3xl space-y-8">
        @if ($status === 'verified')
            <div class="flex items-start gap-4 rounded-xl border border-bone/10 p-6">
                <x-glyph name="shield-check" class="size-8 shrink-0 text-champagne" />
                <div><p class="font-display text-3xl">You are verified</p><p class="mt-1 text-ash">Thank you. There is nothing more to do.</p></div>
            </div>
        @elseif ($status === 'pending')
            <div class="flex items-start gap-4 rounded-xl border border-champagne/40 bg-champagne/[.06] p-6">
                <x-glyph name="clock-3" class="size-8 shrink-0 text-champagne" />
                <div><p class="font-display text-3xl">We are reviewing your documents</p><p class="mt-1 text-mist">This usually takes less than an hour during business hours. We will let you know by SMS. You can request bookings in the meantime.</p>
                    @if ($returnTo === 'book')<a href="{{ route('book', ['resume' => 1]) }}" class="btn btn-gold mt-5">Back to your booking</a>@endif</div>
            </div>
        @else
            @if ($status === 'rejected')
                <div class="rounded-xl border border-red-500/40 bg-red-950/30 p-5" role="alert">
                    <p class="font-semibold">We could not verify your last upload</p>
                    @if ($note)<p class="mt-1 text-sm text-mist">{{ $note }}</p>@endif
                    <p class="mt-1 text-sm text-ash">Please try again with clear, well-lit photos.</p>
                </div>
            @endif
            <p class="text-mist">Every client is verified once. It protects our operators, and it means we know exactly who we are protecting. You will need a photo of your South African ID, driver's licence or passport, and a selfie.</p>

            <form wire:submit="submit" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([['idDocument', 'ID document', 'id-card', 'ID card, licence or passport photo page. JPG, PNG or PDF.', 'image/*,application/pdf', null], ['selfie', 'Selfie', 'scan-face', 'Your face, clearly lit, no sunglasses or hat.', 'image/*', 'user']] as [$field, $label, $icon, $help, $accept, $capture])
                        <label class="group relative flex min-h-48 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-bone/20 p-6 text-center transition-colors hover:border-champagne/60 focus-within:border-champagne">
                            @if ($this->{$field} && str_starts_with((string) $this->{$field}->getMimeType(), 'image/'))
                                <img src="{{ $this->{$field}->temporaryUrl() }}" alt="" class="h-28 rounded-md object-cover">
                                <span class="text-sm text-champagne-light">{{ $label }} added. Tap to replace.</span>
                            @elseif ($this->{$field})
                                <x-glyph name="file-check" class="size-8 text-champagne" /><span class="text-sm text-champagne-light">{{ $this->{$field}->getClientOriginalName() }}</span>
                            @else
                                <x-glyph :name="$icon" class="size-8 text-champagne" />
                                <span class="font-semibold">{{ $label }}</span>
                                <span class="text-sm text-ash">{{ $help }}</span>
                            @endif
                            <input type="file" wire:model="{{ $field }}" accept="{{ $accept }}" @if($capture) capture="{{ $capture }}" @endif class="absolute inset-0 cursor-pointer opacity-0">
                            <span wire:loading wire:target="{{ $field }}" class="text-xs text-ash">Uploading…</span>
                        </label>
                    @endforeach
                </div>
                @error('idDocument')<p class="error">{{ $message }}</p>@enderror
                @error('selfie')<p class="error">{{ $message }}</p>@enderror
                <label class="flex items-start gap-3 text-sm text-mist"><input type="checkbox" wire:model="consent" class="mt-1 accent-champagne"><span>I agree that {{ settings('company.trading_name') }} may use these images to confirm my identity. They are stored securely, seen only by authorised staff, and deleted when I close my account. See the <a class="underline" target="_blank" href="{{ route('legal', 'privacy') }}">privacy policy</a>.</span></label>
                @error('consent')<p class="error">{{ $message }}</p>@enderror
                <button class="btn btn-gold" wire:loading.attr="disabled" wire:target="submit,idDocument,selfie">Submit for review</button>
            </form>
        @endif
    </div>
</x-account-shell>
