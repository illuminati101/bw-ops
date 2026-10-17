<?php

namespace App\Livewire\Account;

use App\Models\Booking;
use App\Services\Payments\PaymentService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.site')]
#[Title('Payment')]
class PaymentMethods extends Component
{
    #[Url(as: 'return')]
    public ?string $returnTo = null;

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->flash = session('payment_flash');
        $this->error = session('payment_error');
    }

    public function addCard(PaymentService $payments): mixed
    {
        $this->error = null;
        $back = route('account.payment', array_filter(['return' => $this->returnTo === 'book' ? 'book' : null]));
        try {
            $result = $payments->beginCardSetup(auth()->user(), url('/payments/callback').'?return='.urlencode($back));
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();

            return null;
        }
        if ($result['redirect_url']) {
            return $this->redirect($result['redirect_url'], navigate: false);
        }
        // Record-only mode (payments not yet connected): the placeholder is saved immediately.
        if ($this->returnTo === 'book') {
            return $this->redirectRoute('book', ['resume' => 1], navigate: false);
        }
        $this->flash = 'Card on file saved. Our team will collect payment after each booking until online card payments go live.';

        return null;
    }

    public function makeDefault(int $id): void
    {
        $u = auth()->user();
        $m = $u->paymentMethods()->findOrFail($id);
        $u->paymentMethods()->update(['is_default' => false]);
        $m->update(['is_default' => true]);
    }

    public function remove(int $id): void
    {
        $m = auth()->user()->paymentMethods()->findOrFail($id);
        if (Booking::query()->where('payment_method_id', $m->id)->open()->exists()) {
            $this->error = 'This card is linked to an upcoming booking. Add another card first, or cancel the booking.';

            return;
        }
        $wasDefault = $m->is_default;
        $m->delete();
        if ($wasDefault && ($next = auth()->user()->paymentMethods()->first())) {
            $next->update(['is_default' => true]);
        }
        $this->flash = 'Card removed.';
    }

    public function render(PaymentService $payments)
    {
        $u = auth()->user();

        return view('livewire.account.payment-methods', [
            'cards' => $u->paymentMethods()->orderByDesc('is_default')->latest('id')->get(),
            'recordOnly' => $payments->isRecordOnly(),
            'needsEmail' => ! $payments->isRecordOnly() && ! $u->email,
            'verifyAmount' => (float) settings('payments.verify_amount'),
            'eft' => (bool) $u->clientProfile?->eft_approved || $u->companies->contains('eft_approved', true),
        ]);
    }
}
