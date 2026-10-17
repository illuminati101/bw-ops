<x-layouts.site :title="$ok ? 'Card saved' : 'Card not saved'" :bare="true">
    <div class="wrap grid min-h-[60vh] place-items-center py-20 text-center">
        <div class="max-w-md">
            <x-glyph :name="$ok ? 'circle-check' : 'circle-alert'" class="mx-auto size-12 {{ $ok ? 'text-champagne' : 'text-red-300' }}" />
            <h1 class="mt-5 text-display-m">{{ $ok ? 'Card saved' : 'Card not saved' }}</h1>
            <p class="mt-4 text-mist">{{ $message }}</p>
            <p class="mt-2 text-sm text-ash">You can close this window and return to the app.</p>
            @auth<a href="{{ route('account.payment') }}" class="btn btn-line mt-8">Go to payment settings</a>@endauth
        </div>
    </div>
</x-layouts.site>
