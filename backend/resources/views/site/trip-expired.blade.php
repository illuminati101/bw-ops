<x-layouts.site title="Trip link expired" :bare="true">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="wrap grid min-h-[60vh] place-items-center py-20 text-center">
        <div class="max-w-md">
            <h1 class="text-display-m">This trip link has ended</h1>
            <p class="mt-4 text-mist">Shared trip links stop working shortly after the trip finishes, to keep everyone's movements private.</p>
            <p class="mt-6 text-sm text-ash">Concerned about someone's safety? Call our control room on <a class="text-champagne-light" href="tel:{{ preg_replace('/\s+/', '', settings('company.control_room_phone')) }}">{{ settings('company.control_room_phone') }}</a>, or 10111 in an emergency.</p>
        </div>
    </div>
</x-layouts.site>
