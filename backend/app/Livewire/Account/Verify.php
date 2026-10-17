<?php

namespace App\Livewire\Account;

use App\Services\Messaging\Notifier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.site')]
#[Title('Verify your identity')]
class Verify extends Component
{
    use WithFileUploads;

    #[Url(as: 'return')]
    public ?string $returnTo = null;

    public $idDocument = null;

    public $selfie = null;

    public bool $consent = false;

    public function submit(Notifier $notifier): mixed
    {
        $this->validate([
            'idDocument' => 'required|file|mimes:jpg,jpeg,png,heic,pdf|max:10240',
            'selfie' => 'required|image|max:10240',
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Please confirm you agree to us checking your ID.'], ['idDocument' => 'ID document']);

        $user = auth()->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);
        $profile->update([
            'id_document_path' => $this->idDocument->store('verification/'.$user->id, 'local'),
            'selfie_path' => $this->selfie->store('verification/'.$user->id, 'local'),
            'verification_status' => 'pending',
            'verification_submitted_at' => now(),
            'verification_notes' => null,
        ]);
        $notifier->staff('ID verification submitted', "{$user->name} uploaded verification documents.", 'clients', 'clients.verify');
        $this->reset('idDocument', 'selfie');

        if ($this->returnTo === 'book') {
            return $this->redirectRoute('book', ['resume' => 1], navigate: false);
        }

        return null;
    }

    public function render()
    {
        $profile = auth()->user()->clientProfile;

        return view('livewire.account.verify', [
            'status' => $profile?->verification_status ?? 'unverified',
            'note' => $profile?->verification_notes,
        ]);
    }
}
