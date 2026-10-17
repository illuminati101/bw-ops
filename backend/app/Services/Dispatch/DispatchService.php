<?php

namespace App\Services\Dispatch;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Events\BookingUpdated;
use App\Events\ControlRoomAlert;
use App\Events\OperatorFeedChanged;
use App\Jobs\ExpireDispatchOffer;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingShift;
use App\Models\DispatchOffer;
use App\Models\OperatorProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Compliance\ComplianceService;
use App\Services\Messaging\Notifier;
use App\Support\Geo;
use App\Support\Realtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * On-demand: offer the job to the nearest eligible operator; if they don't accept in time,
 * cascade to the next. Advance: staff assign operators, who accept in the Crew app.
 */
class DispatchService
{
    public function __construct(private ComplianceService $compliance, private Notifier $notifier) {}

    // ── Requirements ─────────────────────────────────────────────────────

    /** Roles a booking needs filled, e.g. ['driver', 'guard']. */
    public function requiredRoles(Booking $booking): array
    {
        $roles = [];
        $service = $booking->service;
        if ($service->includes_transport) {
            for ($i = 0; $i < max(1, $booking->vehicles_count); $i++) {
                $roles[] = 'driver';
            }
        }
        for ($i = 0; $i < $booking->guards_count; $i++) {
            $roles[] = 'guard';
        }
        if (! $roles) {
            $roles[] = 'guard';
        }

        return $roles;
    }

    /** Roles not yet covered by an accepted/active assignment (optionally for one shift). */
    public function openRoles(Booking $booking, ?BookingShift $shift = null): array
    {
        $needed = $this->requiredRoles($booking);
        $filled = $booking->activeAssignments()
            ->when($shift, fn ($q) => $q->where('booking_shift_id', $shift->id))
            ->when(! $shift, fn ($q) => $q->whereNull('booking_shift_id'))
            ->pluck('role')->all();
        foreach ($filled as $role) {
            $role = $role === 'lead' ? 'guard' : $role;
            $idx = array_search($role, $needed, true);
            if ($idx !== false) {
                unset($needed[$idx]);
            }
        }

        return array_values($needed);
    }

    // ── Candidates ───────────────────────────────────────────────────────

    /**
     * Eligible operators for a role, nearest first.
     *
     * @return Collection<int, array{operator: OperatorProfile, distance_km: ?float, eta_minutes: ?int, vehicle: ?Vehicle}>
     */
    public function candidates(Booking $booking, string $role, bool $requireOnline = true, array $excludeIds = []): Collection
    {
        $required = array_map('intval', $booking->feature_ids ?? []);
        $vehicleFeatureIds = \App\Models\Feature::query()->whereIn('id', $required)->where('applies_to', 'vehicle')->pluck('id')->all();
        $operatorFeatureIds = array_values(array_diff($required, $vehicleFeatureIds));
        $staleMinutes = (int) settings('dispatch.location_stale_minutes');
        $radius = (float) settings('dispatch.max_radius_km');

        $busyIds = $this->busyOperatorIds($booking);
        $pendingOfferIds = DispatchOffer::query()->where('status', 'pending')->where('expires_at', '>', now())->pluck('operator_profile_id')->all();
        $alreadyTried = $booking->offers()->pluck('operator_profile_id')->all();
        $assignedHere = $booking->activeAssignments()->pluck('operator_profile_id')->all();

        $query = OperatorProfile::query()->active()
            ->with(['user', 'features', 'documents', 'currentVehicle.features', 'currentVehicle.documents'])
            ->where('roles', 'like', "%{$role}%")
            ->whereNotIn('id', array_merge($busyIds, $assignedHere, $excludeIds));

        if ($requireOnline) {
            $query->where('is_online', true)
                ->where('last_location_at', '>=', now()->subMinutes($staleMinutes))
                ->whereNotIn('id', array_merge($pendingOfferIds, $alreadyTried));
        }

        return $query->get()
            ->filter(fn (OperatorProfile $op) => $op->user?->status === 'active' && $this->compliance->canWork($op))
            ->filter(fn (OperatorProfile $op) => $role !== 'driver' || $this->compliance->canDrive($op))
            ->filter(function (OperatorProfile $op) use ($operatorFeatureIds) {
                return array_diff($operatorFeatureIds, $this->compliance->effectiveFeatureIds($op)) === [];
            })
            ->map(function (OperatorProfile $op) use ($booking, $role, $vehicleFeatureIds) {
                $vehicle = null;
                if ($role === 'driver') {
                    $vehicle = $op->currentVehicle;
                    if (! $this->compliance->vehicleRoadworthy($vehicle)) {
                        return null;
                    }
                    if (array_diff($vehicleFeatureIds, $this->compliance->effectiveFeatureIds($vehicle)) !== []) {
                        return null;
                    }
                    if ($vehicle->seats < $booking->passengers) {
                        return null;
                    }
                }
                $distance = ($op->last_lat !== null && $op->last_lng !== null)
                    ? Geo::roadKm($op->last_lat, $op->last_lng, $booking->pickup_lat, $booking->pickup_lng) : null;

                return ['operator' => $op, 'distance_km' => $distance, 'eta_minutes' => $distance !== null ? Geo::etaMinutes($distance) : null, 'vehicle' => $vehicle];
            })
            ->filter()
            ->filter(fn ($c) => ! $requireOnline || ($c['distance_km'] !== null && $c['distance_km'] <= $radius))
            ->sortBy(fn ($c) => [
                $c['operator']->id === $booking->preferred_operator_id ? 0 : 1,
                $c['distance_km'] ?? 9999,
                -1 * ($c['operator']->rating_avg ?? 0),
            ])
            ->values();
    }

    /** Operators already committed to an overlapping job. */
    private function busyOperatorIds(Booking $booking): array
    {
        $start = $booking->starts_at->copy()->subMinutes(30);
        $end = $booking->plannedEnd()->copy()->addMinutes(30);

        return BookingAssignment::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, AssignmentStatus::live()))
            ->where('booking_id', '!=', $booking->id)
            ->whereHas('booking', fn ($q) => $q->where('starts_at', '<', $end)
                ->where(fn ($w) => $w->where('ends_at', '>', $start)->orWhereNull('ends_at'))
                ->whereNotIn('status', array_map(fn ($s) => $s->value, BookingStatus::closed())))
            ->distinct()->pluck('operator_profile_id')->all();
    }

    // ── On-demand ────────────────────────────────────────────────────────

    public function startOnDemand(Booking $booking): void
    {
        $booking->update(['status' => BookingStatus::Searching]);
        $booking->logEvent('dispatch.started', 'Searching for the nearest operator');
        foreach (array_unique($this->openRoles($booking)) as $role) {
            $this->offerNext($booking, $role);
        }
        Realtime::send(new BookingUpdated($booking, 'searching'));
    }

    public function offerNext(Booking $booking, string $role): ?DispatchOffer
    {
        $booking->refresh();
        if ($booking->status !== BookingStatus::Searching || ! in_array($role, $this->openRoles($booking), true)) {
            return null;
        }
        $hasPending = $booking->offers()->where('role', $role)->where('status', 'pending')->where('expires_at', '>', now())->exists();
        if ($hasPending) {
            return null;
        }

        $tried = $booking->offers()->count();
        $candidate = $tried < (int) settings('dispatch.max_offers') ? $this->candidates($booking, $role)->first() : null;

        if (! $candidate) {
            $this->handToControlRoom($booking, $role);

            return null;
        }

        $timeout = max(10, (int) settings('dispatch.offer_timeout_seconds'));
        $offer = DispatchOffer::create([
            'booking_id' => $booking->id,
            'operator_profile_id' => $candidate['operator']->id,
            'role' => $role,
            'distance_km' => $candidate['distance_km'],
            'offered_at' => now(),
            'expires_at' => now()->addSeconds($timeout),
        ]);
        $booking->logEvent('dispatch.offered', 'Offered to '.$candidate['operator']->label().($candidate['distance_km'] !== null ? ' ('.number_format($candidate['distance_km'], 1).' km)' : ''));
        $this->notifier->offerToOperator($offer);
        Realtime::send(new OperatorFeedChanged($offer->operator_profile_id, 'offer', ['offer_id' => $offer->id]));
        ExpireDispatchOffer::dispatch($offer->id)->delay(now()->addSeconds($timeout + 1));

        return $offer;
    }

    private function handToControlRoom(Booking $booking, string $role): void
    {
        if (! $booking->escalated_at) {
            $booking->update(['escalated_at' => now()]);
            $booking->logEvent('dispatch.exhausted', 'No operator accepted automatically: handed to the control room');
            Realtime::send(new ControlRoomAlert('dispatch', 'Assign manually: '.$booking->reference, "No available operator accepted the {$role} slot.", ['booking_id' => $booking->id]));
            $this->notifier->staff('Manual dispatch needed', "{$booking->reference}: no operator accepted. Assign from the control room.", 'dispatch');
        }
    }

    public function accept(DispatchOffer $offer, OperatorProfile $operator): BookingAssignment
    {
        return DB::transaction(function () use ($offer, $operator) {
            $offer = DispatchOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($offer->operator_profile_id !== $operator->id) {
                throw ValidationException::withMessages(['offer' => 'This offer is not for you.']);
            }
            if (! $offer->isPending()) {
                throw ValidationException::withMessages(['offer' => 'This offer has expired or was taken.']);
            }
            $booking = $offer->booking()->lockForUpdate()->first();
            if (! in_array($offer->role, $this->openRoles($booking), true) || $booking->status->isClosed()) {
                $offer->update(['status' => 'cancelled', 'responded_at' => now()]);
                throw ValidationException::withMessages(['offer' => 'This job has already been filled.']);
            }
            $offer->update(['status' => 'accepted', 'responded_at' => now()]);
            $assignment = $booking->assignments()->create([
                'operator_profile_id' => $operator->id,
                'vehicle_id' => $offer->role === 'driver' ? $operator->current_vehicle_id : null,
                'role' => $offer->role,
                'status' => AssignmentStatus::Accepted,
                'responded_at' => now(),
            ]);
            $booking->logEvent('dispatch.accepted', $operator->label().' accepted as '.$assignment->roleLabel(), [], $operator->user_id);
            $this->afterAssignmentAccepted($booking);

            return $assignment;
        });
    }

    public function decline(DispatchOffer $offer, OperatorProfile $operator): void
    {
        if ($offer->operator_profile_id !== $operator->id || $offer->status !== 'pending') {
            return;
        }
        $offer->update(['status' => 'declined', 'responded_at' => now()]);
        $offer->booking->logEvent('dispatch.declined', $operator->label().' declined', [], $operator->user_id);
        $this->offerNext($offer->booking, $offer->role);
    }

    public function expire(DispatchOffer $offer): void
    {
        $offer->refresh();
        if ($offer->status !== 'pending' || $offer->expires_at->isFuture()) {
            return;
        }
        $offer->update(['status' => 'expired']);
        $offer->booking->logEvent('dispatch.expired', $offer->operator->label().' did not respond in time');
        Realtime::send(new OperatorFeedChanged($offer->operator_profile_id, 'offer_expired', ['offer_id' => $offer->id]));
        $this->offerNext($offer->booking, $offer->role);
    }

    public function cancelAllOffers(Booking $booking): void
    {
        foreach ($booking->offers()->where('status', 'pending')->get() as $offer) {
            $offer->update(['status' => 'cancelled']);
            Realtime::send(new OperatorFeedChanged($offer->operator_profile_id, 'offer_cancelled', ['offer_id' => $offer->id]));
        }
    }

    // ── Advance / manual assignment ──────────────────────────────────────

    /** Staff assign an operator to a booking (or one shift of it). The operator accepts in the Crew app. */
    public function assign(Booking $booking, OperatorProfile $operator, string $role, ?Vehicle $vehicle, ?User $staff, ?BookingShift $shift = null, bool $preAccepted = false): BookingAssignment
    {
        if ($booking->status->isClosed()) {
            throw ValidationException::withMessages(['booking' => 'This booking is closed.']);
        }
        if ($role === 'driver' && ! $vehicle) {
            $vehicle = $operator->currentVehicle;
        }
        $assignment = $booking->assignments()->create([
            'booking_shift_id' => $shift?->id,
            'operator_profile_id' => $operator->id,
            'vehicle_id' => $role === 'driver' ? $vehicle?->id : null,
            'role' => $role,
            'status' => $preAccepted ? AssignmentStatus::Accepted : AssignmentStatus::Assigned,
            'responded_at' => $preAccepted ? now() : null,
        ]);
        $booking->logEvent('assignment.created', $operator->label().' assigned as '.$assignment->roleLabel().($shift ? ' · '.$shift->label() : ''), [], $staff?->id);
        $this->cancelAllOffersFor($booking, $role);
        if (! $preAccepted) {
            $this->notifier->assignedToOperator($assignment);
        }
        Realtime::send(new OperatorFeedChanged($operator->id, 'assigned', ['booking_id' => $booking->id]));
        $this->afterAssignmentAccepted($booking->fresh());

        return $assignment;
    }

    private function cancelAllOffersFor(Booking $booking, string $role): void
    {
        $booking->offers()->where('status', 'pending')->where('role', $role)->update(['status' => 'cancelled']);
    }

    public function respondToAssignment(BookingAssignment $assignment, OperatorProfile $operator, bool $accept): void
    {
        if ($assignment->operator_profile_id !== $operator->id || $assignment->status !== AssignmentStatus::Assigned) {
            throw ValidationException::withMessages(['assignment' => 'This assignment is no longer awaiting your response.']);
        }
        $assignment->update(['status' => $accept ? AssignmentStatus::Accepted : AssignmentStatus::Declined, 'responded_at' => now()]);
        $booking = $assignment->booking;
        $booking->logEvent($accept ? 'assignment.accepted' : 'assignment.declined', $operator->label().($accept ? ' accepted' : ' declined').' the assignment', [], $operator->user_id);
        if ($accept) {
            $this->afterAssignmentAccepted($booking);
        } else {
            Realtime::send(new ControlRoomAlert('assignment', 'Assignment declined', "{$operator->label()} declined {$booking->reference}.", ['booking_id' => $booking->id]));
            $this->notifier->staff('Assignment declined', "{$operator->label()} declined {$booking->reference}. Please reassign.", 'dispatch');
            Realtime::send(new BookingUpdated($booking, 'assignment_declined'));
        }
    }

    public function removeAssignment(BookingAssignment $assignment, ?User $staff): void
    {
        $assignment->update(['status' => AssignmentStatus::Removed]);
        $booking = $assignment->booking;
        $booking->logEvent('assignment.removed', $assignment->operator->label().' removed', [], $staff?->id);
        if ($booking->status === BookingStatus::Assigned && $this->openRoles($booking)) {
            $booking->update(['status' => BookingStatus::Confirmed]);
        }
        Realtime::send(new OperatorFeedChanged($assignment->operator_profile_id, 'removed', ['booking_id' => $booking->id]));
        Realtime::send(new BookingUpdated($booking, 'assignment_removed'));
    }

    /** If the client asked for a preferred operator and they're free, assign them straight away. */
    public function autoAssignPreferred(Booking $booking): void
    {
        if (! $booking->preferred_operator_id || $booking->structure !== 'single') {
            return;
        }
        $role = $booking->service->includes_transport ? 'driver' : 'guard';
        $candidate = $this->candidates($booking, $role, false)->first(fn ($c) => $c['operator']->id === $booking->preferred_operator_id);
        if ($candidate) {
            $this->assign($booking, $candidate['operator'], $role, $candidate['vehicle'], null);
        }
    }

    private function afterAssignmentAccepted(Booking $booking): void
    {
        $booking->refresh();
        $accepted = $booking->activeAssignments()->where('status', '!=', AssignmentStatus::Assigned->value)->count();
        $allFilled = $booking->shifts()->exists()
            ? $booking->shifts->every(fn ($s) => $this->openRoles($booking, $s) === [])
            : $this->openRoles($booking) === [];
        $pendingResponses = $booking->activeAssignments()->where('status', AssignmentStatus::Assigned->value)->exists();

        if ($allFilled && ! $pendingResponses && in_array($booking->status, [BookingStatus::Searching, BookingStatus::Confirmed], true)) {
            $booking->update(['status' => BookingStatus::Assigned]);
            $booking->logEvent('booking.assigned', 'All operator slots filled');
            $this->notifier->operatorAssigned($booking);
        }
        if ($accepted > 0) {
            foreach ($booking->activeAssignments()->pluck('operator_profile_id') as $op) {
                Realtime::send(new OperatorFeedChanged($op, 'job_updated', ['booking_id' => $booking->id]));
            }
        }
        Realtime::send(new BookingUpdated($booking, 'assignment'));
    }
}
