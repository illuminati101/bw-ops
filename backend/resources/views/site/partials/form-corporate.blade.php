<form method="post" action="{{ route('enquiries.store', 'corporate') }}" class="space-y-5">
    @csrf
    @include('site.partials.flash')
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label" for="k-company">Company</label><input id="k-company" name="company" value="{{ old('company') }}" required class="field" autocomplete="organization">@error('company')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="k-name">Your name</label><input id="k-name" name="name" value="{{ old('name') }}" required class="field" autocomplete="name"></div>
        <div><label class="label" for="k-email">Work email</label><input id="k-email" type="email" name="email" value="{{ old('email') }}" required class="field" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="k-phone">Phone</label><input id="k-phone" name="phone" value="{{ old('phone') }}" required class="field" autocomplete="tel" inputmode="tel"></div>
    </div>
    <div><label class="label" for="k-msg">What do you need?</label><textarea id="k-msg" name="message" rows="5" required class="field" placeholder="For example: protected transfers for visiting executives, about six trips a month, plus security for an annual client event.">{{ old('message') }}</textarea></div>
    <button class="btn btn-gold">Request a company account</button>
</form>
