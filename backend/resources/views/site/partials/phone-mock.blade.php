{{-- Illustration of the client app on the day: built in HTML so it stays sharp and editable. --}}
<div class="relative mx-auto w-full max-w-[22rem]" aria-label="Illustration of the app showing your operator arriving">
    <div class="absolute -inset-y-10 inset-x-0 -z-10 rounded-full bg-champagne/10 blur-3xl sm:-inset-10"></div>
    <div class="rounded-[2.6rem] border border-bone/15 bg-carbon p-3 shadow-[0_40px_120px_-30px_rgba(0,0,0,.9)]">
        <div class="overflow-hidden rounded-[2.1rem] bg-night">
            <div class="relative h-56 overflow-hidden">
                <img src="{{ asset('images/control-room.jpg') }}" alt="" class="absolute inset-0 size-full object-cover opacity-80" loading="lazy">
                <svg class="absolute inset-0 size-full" viewBox="0 0 320 220" fill="none" aria-hidden="true">
                    <path d="M40 190 C 90 150, 120 160, 160 120 S 240 70, 280 40" stroke="#C9A86A" stroke-width="2.5" stroke-dasharray="1 7" stroke-linecap="round"/>
                    <circle cx="280" cy="40" r="7" fill="#EDE8DD"/><circle cx="280" cy="40" r="16" stroke="#EDE8DD" stroke-opacity=".3"/>
                    <circle cx="160" cy="120" r="9" fill="#C9A86A"/><circle cx="160" cy="120" r="20" stroke="#C9A86A" stroke-opacity=".35"/>
                </svg>
            </div>
            <div class="space-y-5 p-5">
                <div>
                    <p class="text-sm text-ash">Arriving in 4 minutes</p>
                    <p class="font-display text-3xl">Sipho is on the way</p>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-bone/10 p-3">
                    <span class="grid size-11 place-items-center rounded-full bg-graphite font-display text-lg text-champagne">S</span>
                    <div class="text-sm leading-snug">
                        <p class="font-semibold">Black Mercedes-Benz E-Class</p>
                        <p class="text-ash">CA 101-BW</p>
                    </div>
                </div>
                <div class="rounded-xl border border-champagne/35 bg-champagne/[.06] p-4">
                    <p class="text-sm text-mist">Your arrival PIN</p>
                    <p class="mt-1 font-display text-4xl tracking-[.4em] text-champagne-light">4821</p>
                    <p class="mt-2 text-xs text-ash">Only get in once your operator has told you this PIN.</p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <span class="rounded-lg border border-bone/10 py-2.5">Chat</span>
                    <span class="rounded-lg border border-bone/10 py-2.5">Share trip</span>
                    <span class="rounded-lg border border-alert/50 py-2.5 text-[#f0a3a5]">Hold for SOS</span>
                </div>
            </div>
        </div>
    </div>
</div>
