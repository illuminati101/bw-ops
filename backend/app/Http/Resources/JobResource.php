<?php

namespace App\Http\Resources;

use App\Enums\AssignmentStatus;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A job as an operator sees it: need-to-know only, and only while the job is live.
 *
 * @mixin \App\Models\BookingAssignment
 */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $b = $this->booking;
        $live = in_array($this->status, AssignmentStatus::live(), true) && ! $b->status->isClosed();
        $op = $this->operator;
        $distance = ($op->last_lat !== null) ? Geo::roadKm($op->last_lat, $op->last_lng, $b->pickup_lat, $b->pickup_lng) : null;

        $base = [
            'id' => $this->id,
            'booking_id' => $b->id,
            'reference' => $b->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'booking_status' => $b->status->value,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'service' => $b->service->name,
            'mode' => $b->mode,
            'starts_at' => ($this->shift?->starts_at ?? $b->starts_at)->toIso8601String(),
            'ends_at' => ($this->shift?->ends_at ?? $b->plannedEnd())->toIso8601String(),
            'payout_amount' => $op->isContractor() ? $this->payout_amount : null,
        ];

        if (! $live) {
            return $base + ['live' => false];
        }

        return $base + [
            'live' => true,
            'principal' => [
                'name' => $b->principalDisplayName(),
                'photo_url' => $b->principal_photo_path ? asset('storage/'.$b->principal_photo_path) : null,
                'passengers' => $b->passengers,
            ],
            'pickup' => ['address' => $b->pickup_address, 'lat' => $b->pickup_lat, 'lng' => $b->pickup_lng],
            'dropoff' => $b->dropoff_address ? ['address' => $b->dropoff_address, 'lat' => $b->dropoff_lat, 'lng' => $b->dropoff_lng] : null,
            'stops' => $b->stops->map(fn ($s) => ['address' => $s->address, 'lat' => $s->lat, 'lng' => $s->lng, 'wait_minutes' => $s->wait_minutes]),
            'duration_minutes' => $b->duration_minutes,
            'requirements' => \App\Models\Feature::query()->whereIn('id', $b->feature_ids ?? [])->pluck('name'),
            'notes' => trim(($b->client_notes ?? '')."\n".($b->operator_notes ?? '')) ?: null,
            'vehicle' => $this->vehicle ? $this->vehicle->label() : null,
            'team' => $b->activeAssignments()->with('operator.user')->where('id', '!=', $this->id)->get()
                ->map(fn ($a) => ['name' => $a->operator->displayName(), 'call_sign' => $a->operator->call_sign, 'role' => $a->roleLabel()]),
            'distance_km' => $distance,
            'eta_minutes' => $distance !== null ? Geo::etaMinutes($distance) : null,
            'pin_required' => feature_enabled('arrival_pin') && ! $b->pin_verified_at,
            'pin_verified' => (bool) $b->pin_verified_at,
            'payment_method' => $b->payment_method,
            'card_machine_due' => $b->payment_method === 'card_machine' && $b->payment_status !== 'paid' ? ($b->final_total ?? $b->quoted_total) : null,
            'no_show_wait_minutes' => (int) settings('cancellation.no_show_wait_minutes'),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'can' => [
                'accept' => $this->status === AssignmentStatus::Assigned,
                'en_route' => $this->status === AssignmentStatus::Accepted,
                'arrived' => in_array($this->status, [AssignmentStatus::Accepted, AssignmentStatus::EnRoute], true),
                'start' => in_array($this->status, [AssignmentStatus::Accepted, AssignmentStatus::EnRoute, AssignmentStatus::Arrived], true),
                'complete' => $this->status === AssignmentStatus::OnJob,
                'no_show' => $this->status === AssignmentStatus::Arrived,
                'chat' => $b->chatOpen(),
                'call' => feature_enabled('masked_calls') && app(\App\Services\Messaging\Channels::class)->twilioReady(),
            ],
        ];
    }
}
