<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Messaging\Notifier;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private Notifier $notifier) {}

    /** Whether codes are shown on screen because SMS isn't connected yet (testing only). */
    public function revealCodes(): bool
    {
        return config('bwops.integrations.sms_driver') !== 'twilio' && ! app()->isProduction();
    }

    /** @return array{phone: string, expires_in: int, dev_code: ?string} */
    public function send(string $rawPhone, string $purpose = 'login', ?string $ip = null): array
    {
        $phone = PhoneNumber::normalize($rawPhone);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Please enter a valid mobile number.']);
        }
        $key = 'otp-send:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 4)) {
            $wait = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['phone' => "Too many codes requested. Please try again in {$wait} seconds."]);
        }
        RateLimiter::hit($key, 600);

        $code = (string) random_int(100000, 999999);
        OtpCode::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::create([
            'phone' => $phone, 'code_hash' => Hash::make($code), 'purpose' => $purpose,
            'expires_at' => now()->addMinutes(10), 'ip' => $ip,
        ]);
        $this->notifier->otp($phone, $code);

        return ['phone' => $phone, 'expires_in' => 600, 'dev_code' => $this->revealCodes() ? $code : null];
    }

    public function verify(string $rawPhone, string $code): string
    {
        $phone = PhoneNumber::normalize($rawPhone);
        $otp = $phone ? OtpCode::query()->where('phone', $phone)->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first() : null;
        if (! $otp) {
            throw ValidationException::withMessages(['code' => 'That code has expired. Please request a new one.']);
        }
        if ($otp->attempts >= 5) {
            $otp->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Please request a new code.']);
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }
        $otp->update(['consumed_at' => now()]);

        return $phone;
    }
}
