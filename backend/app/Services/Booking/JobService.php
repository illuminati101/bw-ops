<?php

namespace App\Services\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Events\BookingUpdated;
use App\Events\ControlRoomAlert;
use App\Events\OperatorLocationUpdated;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\LocationPing;
use App\Models\OperatorProfile;
use App\Services\Messaging\Notifier;
use App\Services\Payments\PaymentService;
use App\Support\Geo;
use App\Support\Money;
use App\Support\Realtime;
use Illuminate\Validation\ValidationException;

/** Operator-side progress of a job: en route → arrived → PIN → in progress → complete. */
class JobService
{
    public function __construct(private Notifier $notifier, private PaymentService $payments, private BookingService $bookings) {}

    public function goOnline(OperatorProfile $op, bool $online, ?float $lat = null, ?float $lng = null): OperatorProfile
    {
        if ($online && ! app(\App\Services\Compliance\ComplianceService::class)->canWork($op)) {
            throw ValidationException::withMessages(['online' => 'You cannot go online until your required documents are valid. Check the Documents tab.']);
        }
        $op->update([
            'is_online' => $online,
            'online_since' => $online ? ($op->online_since ?? now()) : null,
        ] + ($lat !== null ? ['last_lat' => $lat, 'last_lng' => $lng, 'last_location_at' => now()] : []));
        Realtime::send(new OperatorLocationUpdated($op));

        return $op;
    }

    /** @param array<int, array{lat: float, lng: float, heading?: float, speed?: float, accuracy?: float, at?: string}> $points */
    public function recordLocation(OperatorProfile $op, array $points): void
    {
        if (! $points) {
            return;
        }
        $liveBookingIds = $op->assignments()
            ->whereIn('status', array_map(fn ($s) => $s->value, [AssignmentStatus::Accepted, AssignmentStatus::EnRoute, AssignmentStatus::Arrived, AssignmentStatus::OnJob]))
            ->whereHas('booking', fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::active())))
            ->pluck('booking_id')->all();
        $trackingBookingId = $liveBookingIds[0] ?? null;

        $rows = [];
        foreach (array_slice($points, -50) as $p) {
            $rows[] = [
                'operator_profile_id' => $op->id,
                'booking_id' => $trackingBookingId,
                'lat' => (float) $p['lat'], 'lng' => (float) $p['lng'],
                'heading' => $p['heading'] ?? null, 'speed' => $p['speed'] ?? null, 'accuracy' => $p['accuracy'] ?? null,
                'recorded_at' => isset($p['at']) ? \Carbon\Carbon::parse($p['at']) : now(),
            ];
        }
        LocationPing::insert($rows);
        $last = end($rows);
        $op->update(['last_lat' => $last['lat'], 'last_lng' => $last['lng'], 'last_heading' => $last['heading'], 'last_location_at' => $last['recorded_at']]);
        Realtime::send(new OperatorLocationUpdated($op, $liveBookingIds));
    }

    public function update(BookingAssignment $a, OperatorProfile $op, string $action, array $payload = []): BookingAssignment
    {
        if ($a->operator_profile_id !== $op->id) {
            throw ValidationException::withMessages(['job' => 'This job is not assigned to you.']);
        }
        $booking = $a->booking;
        if ($booking->status->isClosed()) {
            throw ValidationException::withMessages(['job' => 'This job is closed.']);
        }

        match ($action) {
            'en_route' => $this->enRoute($a, $booking, $op),
            'arrived' => $this->arrived($a, $booking),
            'start' => $this->start($a, $booking, $payload),
            'complete' => $this->complete($a, $booking, $payload),
            'no_show' => $this->noShow($a, $booking),
            'card_machine_paid' => $this->payments->markCardMachinePaid($booking, $payload['reference'] ?? null),
            default => throw ValidationException::withMessages(['action' => 'Unknown action.']),
        };

        Realtime::send(new BookingUpdated($booking->fresh(), $action));

        return $a->fresh();
    }

    public function verifyPin(Booking $booking, string $pin): bool
    {
        $ok = hash_equals($booking->arrival_pin, preg_replace('/\D/', '', $pin));
        $booking->logEvent($ok ? 'pin.verified' : 'pin.failed', $ok ? 'Arrival PIN verified' : 'Incorrect arrival PIN entered');
        if ($ok) {
            $booking->update(['pin_verified_at' => now()]);
        } else {
            $fails = $booking->events()->where('type', 'pin.failed')->count();
            if ($fails >= 3) {
                Realtime::send(new ControlRoomAlert('pin', 'PIN failures on '.$booking->reference, '3 incorrect PIN attempts. Check in with the client.', ['booking_id' => $booking->id]));
            }
        }

        return $ok;
    }

    private function enRoute(BookingAssignment $a, Booking $booking, OperatorProfile $op): void
    {
        $this->requireStatus($a, [AssignmentStatus::Accepted]);
        $a->update(['status' => AssignmentStatus::EnRoute, 'en_route_at' => now()]);
        if (in_array($booking->status, [BookingStatus::Assigned, BookingStatus::Confirmed, BookingStatus::Searching], true)) {
            $booking->update(['status' => BookingStatus::EnRoute]);
        }
        $eta = ($op->last_lat !== null) ? Geo::etaMinutes(Geo::roadKm($op->last_lat, $op->last_lng, $booking->pickup_lat, $booking->pickup_lng)) : null;
        $booking->logEvent('job.en_route', $op->label().' is on the way', ['eta' => $eta], $op->user_id);
        $this->notifier->operatorEnRoute($booking, $eta);
    }

    private function arrived(BookingAssignment $a, Booking $booking): void
    {
        $this->requireStatus($a, [AssignmentStatus::Accepted, AssignmentStatus::EnRoute]);
        $a->update(['status' => AssignmentStatus::Arrived, 'arrived_at' => now()]);
        if (! in_array($booking->status, [BookingStatus::Arrived, BookingStatus::InProgress], true)) {
            $booking->update(['status' => BookingStatus::Arrived]);
            $this->notifier->operatorArrived($booking);
        }
        $booking->logEvent('job.arrived', $a->operator->label().' arrived at pickup', [], $a->operator->user_id);
    }

    private function start(BookingAssignment $a, Booking $booking, array $payload): void
    {
        $this->requireStatus($a, [AssignmentStatus::Accepted, AssignmentStatus::EnRoute, AssignmentStatus::Arrived]);
        if (feature_enabled('arrival_pin') && ! $booking->pin_verified_at) {
            if (! empty($payload['pin'])) {
                if (! $this->verifyPin($booking, (string) $payload['pin'])) {
                    throw ValidationException::withMessages(['pin' => 'That PIN is incorrect. Do not start the job: ask the client to check their app, or call the control room.']);
                }
            } elseif (! empty($payload['pin_override_reason'])) {
                $booking->logEvent('pin.override', 'PIN check overridden: '.$payload['pin_override_reason'], [], $a->operator->user_id);
                Realtime::send(new ControlRoomAlert('pin', 'PIN override on '.$booking->reference, $payload['pin_override_reason'], ['booking_id' => $booking->id]));
            } else {
                throw ValidationException::withMessages(['pin' => 'Ask the client for their 4-digit arrival PIN before starting.']);
            }
        }
        $a->update(['status' => AssignmentStatus::OnJob, 'started_at' => now()]);
        if ($booking->status !== BookingStatus::InProgress) {
            $booking->update(['status' => BookingStatus::InProgress, 'started_at' => now()]);
            $booking->logEvent('job.started', 'Job started', [], $a->operator->user_id);
        }
    }

    private function complete(BookingAssignment $a, Booking $booking, array $payload): void
    {
        $this->requireStatus($a, [AssignmentStatus::OnJob]);
        $a->update(['status' => AssignmentStatus::Done, 'finished_at' => now()]);
        $this->computePayout($a);

        $remaining = $booking->activeAssignments()->whereNotIn('status', [AssignmentStatus::Done->value])
            ->when($booking->shifts()->exists(), fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('starts_at', '<=', now())))
            ->exists();
        $isLastShift = ! $booking->shifts()->where('starts_at', '>', now())->exists();
        if ($remaining || ! $isLastShift) {
            $booking->logEvent('job.operator_done', $a->operator->label().' finished', [], $a->operator->user_id);

            return;
        }
        $this->finish($booking, isset($payload['actual_km']) ? (float) $payload['actual_km'] : null);
    }

    /** Close the booking, work out overtime and collect payment. */
    public function finish(Booking $booking, ?float $actualKm = null): void
    {
        $started = $booking->started_at ?? $booking->starts_at;
        $actualMinutes = (int) $started->diffInMinutes(now());
        $graceMinutes = (int) ($booking->service->rateCardFor($booking->region)?->included_wait_minutes ?? 15);
        $overtime = 0;
        if (! in_array($booking->structure, ['multi_day', 'recurring'], true)) {
            $over = $actualMinutes - $booking->duration_minutes - $graceMinutes;
            $overtime = $over > 0 ? (int) (ceil($over / 15) * 15) : 0;
        }
        $actualKm ??= $this->trackedKm($booking);
        $extraKmCharge = 0.0;
        if ($booking->service->includes_transport && $actualKm && $booking->estimated_km > 0 && $actualKm > $booking->estimated_km * 1.15) {
            $card = $booking->service->rateCardFor($booking->region);
            $extraKmCharge = round(($actualKm - $booking->estimated_km * 1.15) * (float) ($card?->per_km ?? 0) * max(1, $booking->vehicles_count), 2);
        }
        $final = Money::round($booking->quoted_total + ($overtime / 60) * $booking->overtime_rate + $extraKmCharge);

        $booking->update([
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
            'overtime_minutes' => $overtime,
            'actual_km' => $actualKm,
            'final_total' => $final,
        ]);
        $booking->logEvent('job.completed', 'Job completed · '.rands($final).($overtime ? " (incl. {$overtime} min overtime)" : ''));

        $this->payments->collect($booking, $final, 'charge', 'Booking '.$booking->reference);
        $this->promoteTrust($booking);
        $this->notifier->bookingCompleted($booking->fresh());
    }

    private function noShow(BookingAssignment $a, Booking $booking): void
    {
        $this->requireStatus($a, [AssignmentStatus::Arrived]);
        $wait = (int) settings('cancellation.no_show_wait_minutes');
        $since = max(0, (int) $a->arrived_at->diffInMinutes(now()));
        $mustWaitUntil = max($a->arrived_at, $booking->starts_at)->copy()->addMinutes($wait);
        if (now()->lt($mustWaitUntil)) {
            throw ValidationException::withMessages(['no_show' => "Please wait {$wait} minutes after the booked time and try the client through the app or the control room first ({$since} min so far)."]);
        }
        $this->bookings->recordNoShow($booking, $a->operator->user);
    }

    private function computePayout(BookingAssignment $a): void
    {
        $op = $a->operator;
        if (! $op->isContractor()) {
            return;
        }
        $hours = max(0.5, ($a->started_at ?? $a->arrived_at ?? $a->created_at)->diffInMinutes($a->finished_at ?? now()) / 60);
        $km = $a->role === 'driver' ? (float) ($a->booking->actual_km ?? $a->booking->estimated_km) : 0;
        $a->update(['payout_amount' => Money::round($hours * (float) $op->pay_rate_hourly + $km * (float) $op->pay_rate_km)]);
    }

    private function trackedKm(Booking $booking): ?float
    {
        $pings = LocationPing::query()->where('booking_id', $booking->id)
            ->when($booking->started_at, fn ($q) => $q->where('recorded_at', '>=', $booking->started_at))
            ->orderBy('recorded_at')->get(['lat', 'lng', 'operator_profile_id']);
        if ($pings->count() < 2) {
            return null;
        }
        $byOp = $pings->groupBy('operator_profile_id')->first();
        $km = 0.0;
        for ($i = 1; $i < $byOp->count(); $i++) {
            $km += Geo::distanceKm($byOp[$i - 1]->lat, $byOp[$i - 1]->lng, $byOp[$i]->lat, $byOp[$i]->lng);
        }

        return round($km, 1);
    }

    private function promoteTrust(Booking $booking): void
    {
        $profile = $booking->client->clientProfile;
        if (! $profile || $profile->isTrusted()) {
            return;
        }
        $done = $booking->client->bookings()->where('status', BookingStatus::Completed->value)->count();
        $strikes = $booking->client->activeStrikesCount();
        if ($done >= (int) settings('booking.trusted_after') && $strikes === 0) {
            $profile->update(['trust_level' => 'trusted']);
            $booking->logEvent('client.trusted', 'Client promoted to trusted');
        }
    }

    private function requireStatus(BookingAssignment $a, array $allowed): void
    {
        if (! in_array($a->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'That step is not available right now (current status: '.$a->status->getLabel().').']);
        }
    }
}
