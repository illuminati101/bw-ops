<form method="post" action="{{ route('enquiries.store', 'contact') }}" class="space-y-5">
    @csrf
    @include('site.partials.flash')
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label" for="c-name">Your name</label><input id="c-name" name="name" value="{{ old('name') }}" required class="field" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="c-phone">Mobile number</label><input id="c-phone" name="phone" value="{{ old('phone') }}" class="field" autocomplete="tel" inputmode="tel"></div>
    </div>
    <div><label class="label" for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email') }}" required class="field" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="c-msg">How can we help?</label><textarea id="c-msg" name="message" rows="5" required class="field">{{ old('message') }}</textarea>@error('message')<p class="error">{{ $message }}</p>@enderror</div>
    <p class="text-xs text-ash">We use your details only to reply. See our <a class="underline" href="{{ route('legal', 'privacy') }}">Privacy Policy</a>.</p>
    <button class="btn btn-gold">Send message</button>
</form>
