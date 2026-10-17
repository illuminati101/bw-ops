<?php

namespace App\Services\Auth;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Self-service account deletion (required by Apple and POPIA). Booking and payment records are
     * kept for tax purposes, but anonymised; ID documents, contacts and saved cards are removed.
     */
    public function deleteClient(User $user): void
    {
        if (Booking::query()->where('client_id', $user->id)->open()->exists()) {
            throw ValidationException::withMessages(['account' => 'Please cancel or complete your open bookings before deleting your account.']);
        }
        $user->tokens()->delete();
        $user->deviceTokens()->delete();
        $user->emergencyContacts()->delete();
        $user->paymentMethods()->delete();
        if ($p = $user->clientProfile) {
            foreach (['id_document_path', 'selfie_path'] as $f) {
                if ($p->$f) {
                    Storage::disk('local')->delete($p->$f);
                }
            }
            $p->update(['id_document_path' => null, 'selfie_path' => null, 'code_name' => null, 'staff_notes' => null]);
        }
        Booking::query()->where('client_id', $user->id)->update(['principal_name' => null, 'principal_phone' => null, 'client_notes' => null]);
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['name' => 'Deleted user', 'email' => null, 'phone' => null, 'avatar_path' => null, 'status' => 'suspended', 'safety_pin_hash' => null, 'duress_pin_hash' => null])->save();
        $user->delete();
    }
}
