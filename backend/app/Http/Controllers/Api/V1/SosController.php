<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SosAlert;
use App\Services\Safety\SosService;
use Illuminate\Http\Request;

class SosController extends Controller
{
    public function store(Request $request, SosService $sos)
    {
        $data = $request->validate(['lat' => 'nullable|numeric', 'lng' => 'nullable|numeric', 'booking' => 'nullable|string']);
        $booking = isset($data['booking']) ? Booking::query()->where('reference', $data['booking'])->first() : null;
        $alert = $sos->raise($request->user(), $data['lat'] ?? null, $data['lng'] ?? null, $booking);

        return response()->json(['id' => $alert->id, 'status' => $alert->status, 'message' => 'Our control room has been alerted and is responding. If you are in immediate danger, also call 10111.'], 201);
    }

    public function setPins(Request $request)
    {
        $data = $request->validate([
            'safety_pin' => 'required|digits:4',
            'duress_pin' => 'nullable|digits:4|different:safety_pin',
        ]);
        $request->user()->forceFill([
            'safety_pin_hash' => \Hash::make($data['safety_pin']),
            'duress_pin_hash' => isset($data['duress_pin']) ? \Hash::make($data['duress_pin']) : null,
        ])->save();

        return ['safety_pin_set' => true, 'duress_pin_set' => isset($data['duress_pin'])];
    }

    public function update(Request $request, int $id)
    {
        $alert = SosAlert::query()->where('user_id', $request->user()->id)->findOrFail($id);
        if ($request->filled(['lat', 'lng'])) {
            $alert->update(['lat' => $request->input('lat'), 'lng' => $request->input('lng')]);
        }

        return ['id' => $alert->id, 'status' => $alert->status];
    }

    public function cancel(Request $request, int $id, SosService $sos)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:200', 'pin' => 'nullable|string|max:8']);
        $alert = SosAlert::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $sos->cancelByUser($alert, $request->user(), $data['pin'] ?? null, $data['reason'] ?? 'False alarm (cancelled by user)');
        // Same response whether cancelled or duress: never reveal the difference on screen.

        return ['id' => $alert->id, 'status' => 'cancelled', 'message' => 'Alert cancelled. Our control room may still call to check on you.'];
    }
}
