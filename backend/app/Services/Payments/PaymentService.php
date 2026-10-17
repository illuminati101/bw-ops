<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Messaging\Notifier;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private Notifier $notifier) {}

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= $this->liveGatewayName();

        return $name === 'paystack' ? app(PaystackGateway::class) : app(ManualGateway::class);
    }

    public function liveGatewayName(): string
    {
        return filled(config('bwops.integrations.paystack.secret_key')) ? 'paystack' : 'manual';
    }

    public function isRecordOnly(): bool
    {
        return $this->liveGatewayName() === 'manual';
    }

    public function beginCardSetup(User $user, string $callbackUrl): array
    {
        $result = $this->gateway()->beginCardSetup($user, $callbackUrl);
        Payment::create([
            'user_id' => $user->id, 'type' => 'verify', 'gateway' => $this->gateway()->name(),
            'amount' => $this->isRecordOnly() ? 0 : (float) settings('payments.verify_amount'),
            'status' => $result['method'] ? 'success' : 'pending', 'reference' => $result['reference'],
            'description' => 'Card verification (refunded)',
        ]);

        return $result;
    }

    public function completeCardSetup(User $user, string $reference): PaymentMethod
    {
        $method = $this->gateway()->completeCardSetup($user, $reference);
        Payment::query()->where('reference', $reference)->update(['status' => 'success', 'payment_method_id' => $method->id]);

        return $method;
    }

    /** Place the no-show hold on a booking, if one applies. */
    public function placeHold(Booking $booking): void
    {
        $amount = (float) ($booking->quote_breakdown['hold_amount'] ?? 0);
        $method = $booking->paymentMethodRecord;
        if ($amount <= 0 || ! $method || $booking->payment_method !== 'card' || ! feature_enabled('card_holds')) {
            return;
        }
        if ($this->activeHold($booking)) {
            return;
        }
        $res = $this->gateway($method->gateway)->hold($method, $amount, ['booking' => $booking->reference]);
        $this->record($booking, $method, 'hold', $amount, $res, 'No-show protection hold');
        if ($res['ok']) {
            $booking->update(['payment_status' => 'held']);
            $booking->logEvent('payment.hold', 'Hold placed: '.rands($amount));
        } else {
            $booking->logEvent('payment.hold_failed', 'Hold failed: '.($res['message'] ?? 'unknown'));
        }
    }

    public function releaseHold(Booking $booking): void
    {
        if ($hold = $this->activeHold($booking)) {
            $res = $this->gateway($hold->gateway)->release($hold->gateway_reference ?? $hold->reference);
            $this->record($booking, $hold->paymentMethod, 'release', $hold->amount, $res, 'Hold released', $hold->reference);
        }
    }

    /**
     * Charge an amount for a booking: uses the hold first (capture), then charges any remainder.
     * Returns true when the full amount was collected (or recorded in manual mode).
     */
    public function collect(Booking $booking, float $amount, string $type, string $description): bool
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            $this->releaseHold($booking);

            return true;
        }

        if ($booking->payment_method === 'eft') {
            $booking->update(['payment_status' => 'invoiced']);
            Payment::create(['booking_id' => $booking->id, 'user_id' => $booking->client_id, 'type' => $type, 'gateway' => 'eft', 'amount' => $amount, 'status' => 'pending', 'description' => $description.' (to invoice)']);

            return true;
        }

        if ($booking->payment_method === 'card_machine' && $type === 'charge') {
            Payment::create(['booking_id' => $booking->id, 'user_id' => $booking->client_id, 'type' => $type, 'gateway' => 'card_machine', 'amount' => $amount, 'status' => 'pending', 'description' => $description.' (collect on card machine)']);
            $booking->update(['payment_status' => 'unpaid']);

            return true;
        }

        $method = $booking->paymentMethodRecord ?? $booking->client->defaultPaymentMethod();
        if (! $method) {
            $booking->update(['payment_status' => 'failed']);
            $booking->logEvent('payment.failed', 'No saved card to charge '.rands($amount));
            $this->notifier->paymentFailed($booking, $amount);

            return false;
        }

        $gateway = $this->gateway($method->gateway);
        $remaining = $amount;

        if ($hold = $this->activeHold($booking)) {
            $captureAmount = min($remaining, $hold->amount);
            $res = $gateway->capture($hold->gateway_reference ?? $hold->reference, $captureAmount);
            $this->record($booking, $method, 'capture', $captureAmount, $res, $description.' (from hold)', $hold->reference);
            if ($res['ok']) {
                $remaining = round($remaining - $captureAmount, 2);
            }
        }

        if ($remaining > 0) {
            $ref = 'BWC-'.$booking->reference.'-'.Str::upper(Str::random(5));
            $res = $gateway->charge($method, $remaining, $ref, ['booking' => $booking->reference, 'type' => $type]);
            $this->record($booking, $method, $type, $remaining, $res, $description, $ref);
            if (! $res['ok']) {
                $booking->update(['payment_status' => 'failed']);
                $booking->logEvent('payment.failed', 'Charge failed: '.($res['message'] ?? 'declined'));
                $this->notifier->paymentFailed($booking, $remaining);

                return false;
            }
        }

        $booking->update(['payment_status' => 'paid']);
        $booking->logEvent('payment.collected', 'Collected '.rands($amount).($gateway->name() === 'manual' ? ' (record only)' : ''));

        return true;
    }

    public function markCardMachinePaid(Booking $booking, ?string $reference = null): void
    {
        Payment::query()->where('booking_id', $booking->id)->where('gateway', 'card_machine')->where('status', 'pending')
            ->update(['status' => 'success', 'gateway_reference' => $reference]);
        $booking->update(['payment_status' => 'paid']);
        $booking->logEvent('payment.card_machine', 'Paid on card machine'.($reference ? " ({$reference})" : ''));
    }

    private function activeHold(Booking $booking): ?Payment
    {
        $hold = $booking->payments()->where('type', 'hold')->where('status', 'success')->latest('id')->first();
        if (! $hold) {
            return null;
        }
        $consumed = $booking->payments()->whereIn('type', ['capture', 'release'])->where('status', 'success')->where('reference', $hold->reference)->exists();

        return $consumed ? null : $hold;
    }

    private function record(Booking $booking, ?PaymentMethod $method, string $type, float $amount, array $res, string $description, ?string $linkReference = null): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $booking->client_id,
            'payment_method_id' => $method?->id,
            'type' => $type,
            'gateway' => $method?->gateway ?? 'manual',
            'amount' => $amount,
            'status' => $res['ok'] ? 'success' : 'failed',
            'reference' => $linkReference ?? ($res['reference'] ?? null),
            'gateway_reference' => $res['reference'] ?? null,
            'description' => $description,
            'meta' => array_filter(['message' => $res['message'] ?? null]),
        ]);
    }
}
