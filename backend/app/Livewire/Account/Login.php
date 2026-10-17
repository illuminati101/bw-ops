<?php

namespace App\Livewire\Account;

use App\Models\User;
use App\Services\Auth\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.site')]
#[Title('Sign in')]
class Login extends Component
{
    public string $step = 'phone'; // phone | code | profile

    public string $phone = '';

    public string $code = '';

    public ?string $devCode = null;

    public string $name = '';

    public string $email = '';

    public bool $terms = false;

    public bool $marketing = false;

    public function sendCode(OtpService $otp): void
    {
        $this->validate(['phone' => 'required|string|max:24']);
        $normalized = PhoneNumber::normalize($this->phone);
        if ($normalized && User::query()->where('phone', $normalized)->whereIn('type', ['operator', 'staff'])->exists()) {
            throw ValidationException::withMessages(['phone' => 'This number belongs to a crew or staff account. Please use the Crew app or the admin panel.']);
        }
        $result = $otp->send($this->phone, 'login', request()->ip());
        $this->phone = $result['phone'];
        $this->devCode = $result['dev_code'];
        $this->step = 'code';
        $this->resetErrorBag();
    }

    public function verify(OtpService $otp): mixed
    {
        $this->validate(['code' => 'required|digits:6']);
        $phone = $otp->verify($this->phone, $this->code);
        $user = User::query()->where('phone', $phone)->first();
        if ($user && ! $user->isClient()) {
            throw ValidationException::withMessages(['code' => 'This number is not a client account.']);
        }
        if (! $user) {
            $user = User::create(['name' => 'New client', 'phone' => $phone, 'type' => 'client', 'status' => 'active', 'phone_verified_at' => now()]);
            $user->clientProfile()->create(['verification_status' => 'unverified']);
        }
        if ($user->status === 'blocked') {
            throw ValidationException::withMessages(['code' => 'This account is not active. Please contact us.']);
        }
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'last_seen_at' => now()])->save();
        Auth::login($user, true);
        session()->regenerate();

        if (! $user->email || $user->name === 'New client' || $user->accepted_terms_version !== settings('legal.terms_version')) {
            $this->name = $user->name === 'New client' ? '' : $user->name;
            $this->email = (string) $user->email;
            $this->step = 'profile';

            return null;
        }

        return $this->redirectIntended(route('account'), navigate: false);
    }

    public function saveProfile(): mixed
    {
        $user = auth()->user();
        $this->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'terms' => 'accepted',
        ], ['terms.accepted' => 'Please accept the Terms of Service to continue.']);
        $user->update([
            'name' => $this->name, 'email' => $this->email, 'marketing_opt_in' => $this->marketing,
            'accepted_terms_at' => now(), 'accepted_terms_version' => settings('legal.terms_version'),
        ]);

        return $this->redirectIntended(route('account'), navigate: false);
    }

    public function back(): void
    {
        $this->step = 'phone';
        $this->code = '';
        $this->devCode = null;
    }

    public function render()
    {
        return view('livewire.account.login');
    }
}
