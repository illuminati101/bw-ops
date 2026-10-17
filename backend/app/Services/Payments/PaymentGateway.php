<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\User;

interface PaymentGateway
{
    public function name(): string;

    /** Start saving a card. Returns ['redirect_url' => ?, 'reference' => string, 'method' => ?PaymentMethod]. */
    public function beginCardSetup(User $user, string $callbackUrl): array;

    /** Finish saving a card after the customer returns from the gateway. */
    public function completeCardSetup(User $user, string $reference): PaymentMethod;

    /** @return array{ok: bool, reference: ?string, message: ?string} */
    public function charge(PaymentMethod $method, float $amount, string $reference, array $meta = []): array;

    /** @return array{ok: bool, reference: ?string, message: ?string} */
    public function hold(PaymentMethod $method, float $amount, array $meta = []): array;

    /** @return array{ok: bool, reference: ?string, message: ?string} */
    public function capture(string $holdReference, float $amount): array;

    /** @return array{ok: bool, reference: ?string, message: ?string} */
    public function release(string $holdReference): array;
}
