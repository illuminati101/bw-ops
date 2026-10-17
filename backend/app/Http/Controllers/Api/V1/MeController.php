<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public static function present(User $user): array
    {
        $profile = $user->clientProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->firstName(),
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type->value,
            'status' => $user->status,
            'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            'marketing_opt_in' => $user->marketing_opt_in,
            'terms_accepted' => (bool) $user->accepted_terms_at && $user->accepted_terms_version === settings('legal.terms_version'),
            'verification' => $profile ? [
                'status' => $profile->verification_status,
                'required' => feature_enabled('id_verification'),
                'note' => $profile->verification_status === 'rejected' ? $profile->verification_notes : null,
            ] : null,
            'trusted' => $profile?->isTrusted() ?? false,
            'code_name' => $profile?->code_name,
            'strikes' => feature_enabled('strikes') ? $user->activeStrikesCount() : 0,
            'companies' => $user->companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'role' => $c->pivot->role, 'eft' => $c->eft_approved]),
            'eft_approved' => (bool) ($profile?->eft_approved),
            'has_payment_method' => $user->paymentMethods()->exists(),
            'safety_pin_set' => (bool) $user->safety_pin_hash,
            'duress_pin_set' => (bool) $user->duress_pin_hash,
        ];
    }

    public function show(Request $request)
    {
        return ['user' => self::present($request->user())];
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'email' => ['sometimes', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'marketing_opt_in' => 'sometimes|boolean',
            'accept_terms' => 'sometimes|accepted',
            'code_name' => 'sometimes|nullable|string|max:40',
        ]);
        if (isset($data['accept_terms'])) {
            $user->accepted_terms_at = now();
            $user->accepted_terms_version = settings('legal.terms_version');
        }
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        $user->fill(collect($data)->only(['name', 'email', 'marketing_opt_in'])->all())->save();
        if (array_key_exists('code_name', $data) && $user->clientProfile) {
            $user->clientProfile->update(['code_name' => $data['code_name']]);
        }

        return ['user' => self::present($user->fresh())];
    }

    public function avatar(Request $request)
    {
        $request->validate(['photo' => 'required|image|max:6144']);
        $path = $request->file('photo')->store('avatars', 'public');
        $request->user()->update(['avatar_path' => $path]);

        return ['user' => self::present($request->user()->fresh())];
    }

    public function verification(Request $request)
    {
        $data = $request->validate([
            'id_document' => 'required|file|mimes:jpg,jpeg,png,heic,pdf|max:10240',
            'selfie' => 'required|image|max:10240',
        ]);
        $user = $request->user();
        $profile = $user->clientProfile ?? $user->clientProfile()->create([]);
        $profile->update([
            'id_document_path' => $request->file('id_document')->store('verification/'.$user->id, 'local'),
            'selfie_path' => $request->file('selfie')->store('verification/'.$user->id, 'local'),
            'verification_status' => 'pending',
            'verification_submitted_at' => now(),
        ]);
        app(\App\Services\Messaging\Notifier::class)->staff('ID verification submitted', "{$user->name} uploaded verification documents.", 'clients', 'clients.verify');

        return ['user' => self::present($user->fresh())];
    }

    public function contacts(Request $request)
    {
        return ['contacts' => $request->user()->emergencyContacts()->get(['id', 'name', 'phone', 'relationship', 'notify_on_sos'])];
    }

    public function storeContact(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'phone' => 'required|string|max:24', 'relationship' => 'nullable|string|max:40', 'notify_on_sos' => 'boolean']);
        $data['phone'] = PhoneNumber::normalize($data['phone']) ?? abort(422, 'Invalid phone number');
        abort_if($request->user()->emergencyContacts()->count() >= 5, 422, 'You can add up to 5 emergency contacts.');

        return ['contact' => $request->user()->emergencyContacts()->create($data)];
    }

    public function destroyContact(Request $request, int $id)
    {
        $request->user()->emergencyContacts()->whereKey($id)->delete();

        return response()->noContent();
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:255', 'platform' => 'required|in:ios,android,web', 'app' => 'required|in:client,crew']);
        $request->user()->deviceTokens()->updateOrCreate(['token' => $data['token']], $data + ['last_used_at' => now()]);

        return response()->noContent();
    }

    /** In-app account deletion (required by Apple). Booking records are kept for tax purposes but anonymised. */
    public function destroy(Request $request, \App\Services\Auth\AccountService $accounts)
    {
        $accounts->deleteClient($request->user());

        return response()->noContent();
    }

    public function previousOperators(Request $request)
    {
        $ops = \App\Models\BookingAssignment::query()
            ->whereHas('booking', fn ($q) => $q->where('client_id', $request->user()->id)->where('status', 'completed'))
            ->with('operator.user')->latest('id')->get()->pluck('operator')->unique('id')->filter(fn ($o) => $o && $o->status === 'active')
            ->map(fn ($o) => ['id' => $o->id, 'first_name' => $o->displayName(), 'call_sign' => $o->call_sign, 'photo_url' => $o->user?->avatar_path ? asset('storage/'.$o->user->avatar_path) : null])
            ->values();

        return ['operators' => $ops];
    }
}
