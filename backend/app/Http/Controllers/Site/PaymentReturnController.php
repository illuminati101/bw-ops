<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Where Paystack sends the browser after card setup (website and apps), plus its server-to-server webhook.
 * The callback works without a web session because the apps open it in a system browser.
 */
class PaymentReturnController extends Controller
{
    public function callback(Request $request, PaymentService $payments)
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref'));
        $return = $this->safeReturn($request->query('return'));
        $payment = $reference ? Payment::query()->where('reference', $reference)->where('type', 'verify')->first() : null;

        if (! $payment || ! $payment->user) {
            return $this->finish($return, false, 'We could not find that card confirmation. Please try again.');
        }
        if ($payment->status === 'success') {
            return $this->finish($return, true, 'Card saved.');
        }
        try {
            $method = $payments->completeCardSetup($payment->user, $reference);
        } catch (ValidationException $e) {
            $payment->update(['status' => 'failed']);

            return $this->finish($return, false, collect($e->errors())->flatten()->first());
        }

        return $this->finish($return, true, $method->label().' saved. You are all set to book.');
    }

    /** Fallback page when no return address was given (e.g. an app that has already closed its browser). */
    public function done(Request $request)
    {
        return view('site.payment-return', [
            'ok' => $request->query('status') !== 'failed',
            'message' => (string) $request->query('message', 'You can close this window and return to the app.'),
        ]);
    }

    /** Paystack webhook. Signed with HMAC-SHA512 of the raw body using the secret key. */
    public function webhook(Request $request)
    {
        $secret = (string) config('bwops.integrations.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature');
        if ($secret === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return response()->json(['ok' => false], 401);
        }
        $event = (string) $request->input('event');
        $data = (array) $request->input('data', []);
        $reference = $data['reference'] ?? ($data['transaction_reference'] ?? null);
        Log::channel('stack')->info('Paystack webhook', ['event' => $event, 'reference' => $reference]);

        if ($reference && ($payment = Payment::query()->where('reference', $reference)->orWhere('gateway_reference', $reference)->first())) {
            $status = match ($event) {
                'charge.success' => 'success',
                'charge.failed' => 'failed',
                'refund.processed' => 'refunded',
                default => null,
            };
            if ($status && $payment->status !== $status && ! ($payment->type === 'verify' && $status === 'refunded')) {
                $payment->update(['status' => $status, 'meta' => array_merge($payment->meta ?? [], ['webhook' => $event, 'webhook_at' => now()->toIso8601String()])]);
                if ($status === 'success' && $payment->booking && $payment->booking->payment_status !== 'paid' && in_array($payment->type, ['charge', 'final'], true)) {
                    $payment->booking->update(['payment_status' => 'paid']);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function finish(string $return, bool $ok, string $message)
    {
        $sep = str_contains($return, '?') ? '&' : '?';
        $target = $return.$sep.http_build_query(['status' => $ok ? 'success' : 'failed', 'message' => $message]);
        if (str_starts_with($return, url('/'))) {
            session()->flash($ok ? 'payment_flash' : 'payment_error', $message);
        }

        return redirect()->away($target);
    }

    /** Only same-site addresses or the apps' own URL schemes, never an open redirect. */
    private function safeReturn(?string $return): string
    {
        $fallback = url('/payments/return');
        if (! $return) {
            return $fallback;
        }
        if (str_starts_with($return, '/') && ! str_starts_with($return, '//')) {
            return url($return);
        }
        $scheme = strtolower((string) parse_url($return, PHP_URL_SCHEME));
        if (in_array($scheme, config('bwops.integrations.app_schemes', []), true)) {
            return $return;
        }
        if (in_array($scheme, ['http', 'https'], true) && parse_url($return, PHP_URL_HOST) === request()->getHost()) {
            return $return;
        }

        return $fallback;
    }
}
