<?php

namespace App\Services\Safety;

use App\Events\ControlRoomAlert;
use App\Jobs\EscalateSos;
use App\Models\Booking;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\Messaging\Notifier;
use App\Support\Realtime;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SosService
{
    public function __construct(private Notifier $notifier) {}

    public function raise(User $user, ?float $lat, ?float $lng, ?Booking $booking = null): SosAlert
    {
        if (! feature_enabled('sos')) {
            throw ValidationException::withMessages(['sos' => 'Please call '.settings('company.control_room_phone').' or 10111.']);
        }
        $booking ??= $this->currentBooking($user);
        $existing = SosAlert::query()->where('user_id', $user->id)->where('status', 'open')->where('created_at', '>', now()->subMinutes(30))->first();
        if ($existing) {
            $existing->update(array_filter(['lat' => $lat, 'lng' => $lng]));

            return $existing;
        }

        $sos = SosAlert::create([
            'user_id' => $user->id,
            'booking_id' => $booking?->id,
            'raised_by_role' => $user->isOperator() ? 'operator' : 'client',
            'lat' => $lat,
            'lng' => $lng,
        ]);
        $booking?->logEvent('sos.raised', 'SOS raised by '.$sos->raised_by_role, ['sos_id' => $sos->id], $user->id);

        Realtime::send(new ControlRoomAlert('sos', 'SOS · '.$user->name, $booking ? "Booking {$booking->reference}" : 'No active booking', [
            'sos_id' => $sos->id, 'lat' => $lat, 'lng' => $lng, 'booking_id' => $booking?->id, 'sound' => true,
        ]));
        $this->notifier->sosRaised($sos);
        EscalateSos::dispatch($sos->id)->delay(now()->addSeconds((int) settings('escalation.sos_ack_seconds')));

        return $sos;
    }

    /**
     * False-alarm cancel by the person who raised it. If they have a safety PIN it must match.
     * Entering the duress PIN looks exactly like a successful cancel on their screen, but the
     * alert stays open and is escalated as DURESS.
     *
     * @return bool true if actually cancelled (callers must not reveal the difference)
     */
    public function cancelByUser(SosAlert $sos, User $user, ?string $pin, string $reason = 'Cancelled by user'): bool
    {
        if ($sos->user_id !== $user->id || ! in_array($sos->status, ['open', 'acknowledged'], true)) {
            throw ValidationException::withMessages(['sos' => 'This alert cannot be cancelled.']);
        }
        $pin = $pin !== null ? preg_replace('/\D/', '', $pin) : null;
        if ($user->duress_pin_hash && $pin && Hash::check($pin, $user->duress_pin_hash)) {
            $sos->update(['notes' => trim(($sos->notes ?? '')."\nDURESS PIN ENTERED ".now()->format('H:i:s'))]);
            $sos->booking?->logEvent('sos.duress', 'DURESS PIN entered on SOS cancel', ['sos_id' => $sos->id], $user->id);
            Realtime::send(new ControlRoomAlert('sos', 'DURESS · '.$user->name, 'Duress PIN entered while "cancelling" the SOS. Treat as live.', ['sos_id' => $sos->id, 'lat' => $sos->lat, 'lng' => $sos->lng, 'sound' => true]));
            $this->notifier->escalateToOnCall('DURESS SOS', "{$user->name} entered their duress PIN. Treat as a live emergency.", 2);

            return false;
        }
        if ($user->safety_pin_hash && (! $pin || ! Hash::check($pin, $user->safety_pin_hash))) {
            throw ValidationException::withMessages(['pin' => 'Incorrect safety PIN.']);
        }
        $sos->update(['status' => 'cancelled', 'resolved_at' => now(), 'notes' => $reason]);
        Realtime::send(new ControlRoomAlert('sos_cancelled', 'SOS cancelled · '.$user->name, $reason, ['sos_id' => $sos->id]));

        return true;
    }

    public function acknowledge(SosAlert $sos, User $staff): void
    {
        if ($sos->acknowledged_at) {
            return;
        }
        $sos->update(['status' => 'acknowledged', 'acknowledged_by' => $staff->id, 'acknowledged_at' => now()]);
        $sos->booking?->logEvent('sos.acknowledged', 'SOS acknowledged by '.$staff->name, [], $staff->id);
        Realtime::send(new ControlRoomAlert('sos_ack', 'SOS acknowledged', $staff->name.' is responding', ['sos_id' => $sos->id]));
    }

    public function resolve(SosAlert $sos, User $staff, string $outcome, ?string $notes): void
    {
        $sos->update(['status' => $outcome === 'false_alarm' ? 'false_alarm' : 'resolved', 'resolved_at' => now(), 'notes' => $notes,
            'acknowledged_by' => $sos->acknowledged_by ?? $staff->id, 'acknowledged_at' => $sos->acknowledged_at ?? now()]);
        $sos->booking?->logEvent('sos.resolved', 'SOS closed: '.$outcome, ['notes' => $notes], $staff->id);
    }

    public function escalate(SosAlert $sos): void
    {
        $sos->refresh();
        if (! $sos->isOpen()) {
            return;
        }
        $level = $sos->escalation_level + 1;
        $sos->update(['escalation_level' => $level, 'last_escalated_at' => now()]);
        $where = $sos->lat ? "near {$sos->lat}, {$sos->lng}" : 'location unknown';
        $this->notifier->escalateToOnCall('UNACKNOWLEDGED SOS', "{$sos->user->name} ({$sos->raised_by_role}) {$where}.", min(2, $level));
        if ($level < 4) {
            EscalateSos::dispatch($sos->id)->delay(now()->addSeconds((int) settings('escalation.sos_ack_seconds')));
        }
    }

    private function currentBooking(User $user): ?Booking
    {
        if ($user->isOperator()) {
            return Booking::query()->live()->whereHas('activeAssignments', fn ($q) => $q->where('operator_profile_id', $user->operatorProfile?->id))->latest('starts_at')->first();
        }

        return Booking::query()->live()->visibleTo($user)->latest('starts_at')->first();
    }
}
