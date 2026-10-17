<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Record-only stand-in used until Paystack keys are added.
 * Saving a "card" creates a placeholder; charges are recorded for staff to collect manually.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function beginCardSetup(User $user, string $callbackUrl): array
    {
        $method = $this->completeCardSetup($user, 'manual-'.Str::random(10));

        return ['redirect_url' => null, 'reference' => 'manual', 'method' => $method];
    }

    public function completeCardSetup(User $user, string $reference): PaymentMethod
    {
        $existing = $user->paymentMethods()->where('gateway', 'manual')->first();
        if ($existing) {
            return $existing;
        }

        return $user->paymentMethods()->create([
            'gateway' => 'manual',
            'brand' => 'Card on file',
            'last4' => '0000',
            'reusable' => true,
            'is_default' => ! $user->paymentMethods()->exists(),
            'verified_at' => now(),
        ]);
    }

    public function charge(PaymentMethod $method, float $amount, string $reference, array $meta = []): array
    {
        return ['ok' => true, 'reference' => $reference, 'message' => 'Recorded only (payments not yet connected): collect manually.'];
    }

    public function hold(PaymentMethod $method, float $amount, array $meta = []): array
    {
        return ['ok' => true, 'reference' => 'manual-hold-'.Str::random(8), 'message' => 'Hold recorded only.'];
    }

    public function capture(string $holdReference, float $amount): array
    {
        return ['ok' => true, 'reference' => $holdReference, 'message' => 'Capture recorded only.'];
    }

    public function release(string $holdReference): array
    {
        return ['ok' => true, 'reference' => $holdReference, 'message' => null];
    }
}
