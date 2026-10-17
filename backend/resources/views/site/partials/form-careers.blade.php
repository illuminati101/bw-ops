<form method="post" action="{{ route('careers.apply') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @include('site.partials.flash')
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="grid gap-5 sm:grid-cols-2">
        <div><label class="label" for="j-name">Full name</label><input id="j-name" name="name" value="{{ old('name') }}" required class="field" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="j-phone">Mobile number</label><input id="j-phone" name="phone" value="{{ old('phone') }}" required class="field" autocomplete="tel" inputmode="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="j-email">Email</label><input id="j-email" type="email" name="email" value="{{ old('email') }}" required class="field" autocomplete="email"></div>
        <div><label class="label" for="j-pos">Position</label>
            <select id="j-pos" name="position" class="field" required>
                <option value="driver" @selected(old('position') === 'driver')>Chauffeur / driver</option>
                <option value="protection" @selected(old('position') === 'protection')>Close protection officer</option>
                <option value="both" @selected(old('position') === 'both')>Both</option>
            </select></div>
        <div><label class="label" for="j-years">Years of experience</label><input id="j-years" type="number" min="0" max="50" name="years_experience" value="{{ old('years_experience', 0) }}" class="field"></div>
        <div><label class="label" for="j-psira">PSIRA number and grade</label>
            <div class="flex gap-2"><input id="j-psira" name="psira_number" value="{{ old('psira_number') }}" class="field" placeholder="Number"><input name="psira_grade" value="{{ old('psira_grade') }}" class="field w-24" placeholder="Grade" maxlength="2"></div></div>
    </div>
    <div class="flex flex-wrap gap-6">
        <label class="flex items-center gap-2"><input type="checkbox" name="firearm_competent" value="1" class="accent-champagne" @checked(old('firearm_competent'))> Valid firearm competency</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="has_prdp" value="1" class="accent-champagne" @checked(old('has_prdp'))> Valid PrDP</label>
    </div>
    <div><label class="label" for="j-files">CV and certificates</label><input id="j-files" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="field file:mr-4 file:rounded file:border-0 file:bg-bone file:px-3 file:py-1.5 file:text-night"><p class="hint">Up to 5 files, 8 MB each.</p>@error('files.*')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="j-msg">Anything else we should know?</label><textarea id="j-msg" name="message" rows="4" class="field">{{ old('message') }}</textarea></div>
    <p class="text-xs text-ash">We keep unsuccessful applications for 12 months, as described in our <a class="underline" href="{{ route('legal', 'privacy') }}">Privacy Policy</a>.</p>
    <button class="btn btn-gold">Submit application</button>
</form>
