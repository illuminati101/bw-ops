<section class="wrap py-16 md:py-24">
    <div class="grid gap-14 lg:grid-cols-[1fr_1.4fr]">
        <div class="space-y-10">
            <div>
                <p class="text-mist">24/7 control room</p>
                <a href="tel:{{ preg_replace('/\s+/', '', settings('company.control_room_phone')) }}" class="mt-1 block font-display text-4xl text-champagne-light">{{ settings('company.control_room_phone') }}</a>
                <p class="mt-2 text-sm text-ash">For live bookings, changes on the day and emergencies.</p>
            </div>
            <div>
                <p class="text-mist">Office</p>
                <a href="tel:{{ preg_replace('/\s+/', '', settings('company.phone')) }}" class="mt-1 block text-xl">{{ settings('company.phone') }}</a>
                <a href="mailto:{{ settings('company.email') }}" class="mt-1 block text-xl">{{ settings('company.email') }}</a>
            </div>
            <div>
                <p class="text-mist">Address</p>
                <p class="mt-1 whitespace-pre-line">{{ settings('company.physical_address') }}</p>
            </div>
        </div>
        <div class="panel p-6 md:p-10">
            <h2 class="mb-6 text-display-s">{{ $data['heading'] ?? 'Send a message' }}</h2>
            @include('site.partials.form-contact')
        </div>
    </div>
</section>
