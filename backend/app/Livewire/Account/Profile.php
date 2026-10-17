<?php

namespace App\Livewire\Account;

use App\Services\Auth\AccountService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.site')]
#[Title('Profile and safety')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $codeName = '';

    public bool $marketing = false;

    public $photo = null;

    public string $contactName = '';

    public string $contactPhone = '';

    public string $contactRelationship = '';

    public bool $contactNotify = true;

    public string $safetyPin = '';

    public string $duressPin = '';

    public string $deleteConfirm = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $u = auth()->user();
        $this->name = (string) $u->name;
        $this->email = (string) $u->email;
        $this->codeName = (string) $u->clientProfile?->code_name;
        $this->marketing = (bool) $u->marketing_opt_in;
    }

    public function saveDetails(): void
    {
        $u = auth()->user();
        $this->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($u->id)],
            'codeName' => 'nullable|string|max:40',
            'photo' => 'nullable|image|max:6144',
        ], [], ['codeName' => 'code name']);
        if ($this->email !== $u->email) {
            $u->email_verified_at = null;
        }
        $u->fill(['name' => $this->name, 'email' => $this->email, 'marketing_opt_in' => $this->marketing]);
        if ($this->photo) {
            $u->avatar_path = $this->photo->store('avatars', 'public');
            $this->photo = null;
        }
        $u->save();
        ($u->clientProfile ?? $u->clientProfile()->create([]))->update(['code_name' => $this->codeName ?: null]);
        $this->saved = 'details';
    }

    public function addContact(): void
    {
        $this->validate([
            'contactName' => 'required|string|max:80',
            'contactPhone' => 'required|string|max:24',
            'contactRelationship' => 'nullable|string|max:40',
        ], [], ['contactName' => 'name', 'contactPhone' => 'mobile number']);
        $phone = PhoneNumber::normalize($this->contactPhone);
        if (! $phone) {
            throw ValidationException::withMessages(['contactPhone' => 'Enter a valid mobile number, for example 082 123 4567.']);
        }
        $u = auth()->user();
        if ($u->emergencyContacts()->count() >= 5) {
            throw ValidationException::withMessages(['contactName' => 'You can add up to 5 emergency contacts.']);
        }
        $u->emergencyContacts()->create(['name' => $this->contactName, 'phone' => $phone, 'relationship' => $this->contactRelationship ?: null, 'notify_on_sos' => $this->contactNotify]);
        $this->reset('contactName', 'contactPhone', 'contactRelationship');
        $this->contactNotify = true;
    }

    public function removeContact(int $id): void
    {
        auth()->user()->emergencyContacts()->whereKey($id)->delete();
    }

    public function savePins(): void
    {
        $this->validate([
            'safetyPin' => 'required|digits:4',
            'duressPin' => 'nullable|digits:4|different:safetyPin',
        ], ['duressPin.different' => 'Your duress PIN must be different from your safety PIN.'], ['safetyPin' => 'safety PIN', 'duressPin' => 'duress PIN']);
        auth()->user()->forceFill([
            'safety_pin_hash' => Hash::make($this->safetyPin),
            'duress_pin_hash' => $this->duressPin !== '' ? Hash::make($this->duressPin) : null,
        ])->save();
        $this->reset('safetyPin', 'duressPin');
        $this->saved = 'pins';
    }

    public function deleteAccount(AccountService $accounts): mixed
    {
        if (mb_strtolower(trim($this->deleteConfirm)) !== 'delete') {
            $this->addError('deleteConfirm', 'Type DELETE to confirm.');

            return null;
        }
        try {
            $accounts->deleteClient(auth()->user());
        } catch (ValidationException $e) {
            $this->addError('deleteConfirm', collect($e->errors())->flatten()->first());

            return null;
        }
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Your account has been deleted.');

        return $this->redirectRoute('home', navigate: false);
    }

    public function render()
    {
        $u = auth()->user();

        return view('livewire.account.profile', [
            'user' => $u,
            'contacts' => $u->emergencyContacts()->get(),
            'pinSet' => (bool) $u->safety_pin_hash,
            'duressSet' => (bool) $u->duress_pin_hash,
        ]);
    }
}
