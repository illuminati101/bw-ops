<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Paystack (South Africa): saved cards, charge-after-service, pre-authorisation holds. */
class PaystackGateway implements PaymentGateway
{
    private function api(): PendingRequest
    {
        return Http::baseUrl('https://api.paystack.co')
            ->withToken((string) config('bwops.integrations.paystack.secret_key'))
            ->acceptJson()->asJson()->timeout(20);
    }

    public function name(): string
    {
        return 'paystack';
    }

    public function beginCardSetup(User $user, string $callbackUrl): array
    {
        if (! $user->email) {
            throw ValidationException::withMessages(['email' => 'Please add an email address to your account before saving a card.']);
        }
        $reference = 'BWV-'.Str::upper(Str::random(12));
        $res = $this->api()->post('/transaction/initialize', [
            'email' => $user->email,
            'amount' => Money::toCents((float) settings('payments.verify_amount', 2)),
            'currency' => 'ZAR',
            'reference' => $reference,
            'channels' => ['card'],
            'callback_url' => $callbackUrl,
            'metadata' => ['purpose' => 'card_verification', 'user_id' => $user->id],
        ]);
        if (! $res->json('status')) {
            throw ValidationException::withMessages(['card' => $res->json('message') ?: 'Could not start card verification.']);
        }

        return ['redirect_url' => $res->json('data.authorization_url'), 'reference' => $reference, 'method' => null];
    }

    public function completeCardSetup(User $user, string $reference): PaymentMethod
    {
        $res = $this->api()->get('/transaction/verify/'.rawurlencode($reference));
        $data = $res->json('data');
        if (! $res->json('status') || ($data['status'] ?? null) !== 'success') {
            throw ValidationException::withMessages(['card' => 'Card verification was not completed.']);
        }
        if ((int) ($data['metadata']['user_id'] ?? 0) !== $user->id) {
            throw ValidationException::withMessages(['card' => 'This verification belongs to a different account.']);
        }
        $auth = $data['authorization'];
        if (! ($auth['reusable'] ?? false)) {
            throw ValidationException::withMessages(['card' => 'This card cannot be saved for later payments. Please try another card.']);
        }

        // Refund the verification amount straight away.
        $this->api()->post('/refund', ['transaction' => $reference]);

        $existing = $user->paymentMethods()->where('signature', $auth['signature'] ?? null)->first();
        $attrs = [
            'gateway' => 'paystack',
            'authorization_code' => $auth['authorization_code'],
            'signature' => $auth['signature'] ?? null,
            'email' => $data['customer']['email'] ?? $user->email,
            'brand' => $auth['card_type'] ?? $auth['brand'] ?? 'card',
            'last4' => $auth['last4'] ?? null,
            'exp_month' => $auth['exp_month'] ?? null,
            'exp_year' => $auth['exp_year'] ?? null,
            'bank' => $auth['bank'] ?? null,
            'reusable' => true,
            'verified_at' => now(),
        ];
        if ($existing) {
            $existing->update($attrs);

            return $existing;
        }

        return $user->paymentMethods()->create($attrs + ['is_default' => ! $user->paymentMethods()->exists()]);
    }

    public function charge(PaymentMethod $method, float $amount, string $reference, array $meta = []): array
    {
        $res = $this->api()->post('/transaction/charge_authorization', [
            'authorization_code' => $method->authorization_code,
            'email' => $method->email ?: $method->user->email,
            'amount' => Money::toCents($amount),
            'currency' => 'ZAR',
            'reference' => $reference,
            'metadata' => $meta,
        ]);
        $ok = $res->json('status') && $res->json('data.status') === 'success';

        return ['ok' => $ok, 'reference' => $res->json('data.reference') ?? $reference, 'message' => $ok ? null : ($res->json('data.gateway_response') ?? $res->json('message'))];
    }

    public function hold(PaymentMethod $method, float $amount, array $meta = []): array
    {
        $res = $this->api()->post('/preauthorization/reserve_authorization', [
            'authorization_code' => $method->authorization_code,
            'email' => $method->email ?: $method->user->email,
            'amount' => Money::toCents($amount),
            'currency' => 'ZAR',
            'metadata' => $meta,
        ]);
        $ok = (bool) $res->json('status');

        return ['ok' => $ok, 'reference' => $res->json('data.reference'), 'message' => $ok ? null : $res->json('message')];
    }

    public function capture(string $holdReference, float $amount): array
    {
        $res = $this->api()->post('/preauthorization/capture', [
            'reference' => $holdReference, 'currency' => 'ZAR', 'amount' => (string) Money::toCents($amount),
        ]);
        $ok = (bool) $res->json('status');

        return ['ok' => $ok, 'reference' => $holdReference, 'message' => $ok ? null : $res->json('message')];
    }

    public function release(string $holdReference): array
    {
        $res = $this->api()->post('/preauthorization/release', ['reference' => $holdReference]);
        $ok = (bool) $res->json('status');

        return ['ok' => $ok, 'reference' => $holdReference, 'message' => $ok ? null : $res->json('message')];
    }
}
