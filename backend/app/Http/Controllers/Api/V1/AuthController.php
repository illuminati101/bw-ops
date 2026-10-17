<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestCode(Request $request, OtpService $otp)
    {
        $data = $request->validate(['phone' => 'required|string|max:24', 'app' => 'nullable|in:client,crew']);
        $phone = \App\Support\PhoneNumber::normalize($data['phone']);
        if (($data['app'] ?? 'client') === 'crew' && (! $phone || ! User::query()->where('phone', $phone)->where('type', 'operator')->exists())) {
            throw ValidationException::withMessages(['phone' => 'This number is not registered as crew. Please contact the operations team.']);
        }
        $result = $otp->send($data['phone'], 'login', $request->ip());

        return response()->json([
            'phone' => $result['phone'],
            'expires_in' => $result['expires_in'],
            'existing' => User::query()->where('phone', $result['phone'])->exists(),
            'dev_code' => $result['dev_code'],
        ]);
    }

    public function verifyCode(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:24',
            'code' => 'required|string|max:8',
            'app' => 'nullable|in:client,crew',
            'device_name' => 'nullable|string|max:100',
        ]);
        $app = $data['app'] ?? 'client';
        $phone = $otp->verify($data['phone'], $data['code']);
        $user = User::query()->where('phone', $phone)->first();

        if ($app === 'crew') {
            if (! $user || ! $user->isOperator() || ! $user->operatorProfile) {
                throw ValidationException::withMessages(['phone' => 'This number is not registered as crew.']);
            }
        } else {
            if ($user && $user->isOperator()) {
                throw ValidationException::withMessages(['phone' => 'This is a crew number. Please use the Crew app.']);
            }
            if (! $user) {
                $user = User::create(['name' => 'New client', 'phone' => $phone, 'type' => 'client', 'status' => 'active']);
                $user->clientProfile()->create(['verification_status' => 'unverified']);
            }
        }
        if (in_array($user->status, ['blocked'], true)) {
            throw ValidationException::withMessages(['phone' => 'This account is not active. Please contact us.']);
        }
        if (! $user->phone_verified_at) {
            $user->phone_verified_at = now();
        }
        $user->last_seen_at = now();
        $user->save();

        $token = $user->createToken(($data['device_name'] ?? 'device').' · '.$app, [$app])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => MeController::present($user->fresh()),
            'needs_profile' => $app === 'client' && (! $user->email || $user->name === 'New client' || ! $user->accepted_terms_at),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        if ($token = $request->input('push_token')) {
            $request->user()->deviceTokens()->where('token', $token)->delete();
        }

        return response()->noContent();
    }
}
