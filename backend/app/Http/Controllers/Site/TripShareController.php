<?php

namespace App\Http\Controllers\Site;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TripShare;
use App\Support\Geo;

/** Public "follow my trip" links. Shows only what a trusted contact needs: status, operator, vehicle and position. */
class TripShareController extends Controller
{
    private function share(string $token): ?TripShare
    {
        return TripShare::query()->where('token', $token)->with('booking.service')->first();
    }

    public function show(string $token)
    {
        $share = $this->share($token);
        if (! $share || $share->expires_at->isPast()) {
            return response()->view('site.trip-expired', [], 410);
        }

        return response()->view('site.trip', ['token' => $token, 'data' => $this->payload($share)])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function data(string $token)
    {
        $share = $this->share($token);
        abort_if(! $share || $share->expires_at->isPast(), 410);

        return response()->json($this->payload($share))->header('Cache-Control', 'no-store');
    }

    private function payload(TripShare $share): array
    {
        $b = $share->booking;
        $assignments = $b->activeAssignments()->with(['operator.user', 'vehicle'])->get();
        $lead = $assignments->firstWhere('role', 'driver') ?? $assignments->first();
        $tracking = in_array($b->status, [BookingStatus::Assigned, BookingStatus::EnRoute, BookingStatus::Arrived, BookingStatus::InProgress], true);
        $op = $lead?->operator;
        $eta = null;
        if ($op && $op->last_lat !== null && in_array($b->status, [BookingStatus::Assigned, BookingStatus::EnRoute], true)) {
            $eta = Geo::etaMinutes(Geo::roadKm($op->last_lat, $op->last_lng, $b->pickup_lat, $b->pickup_lng));
        }

        return [
            'status' => $b->status->value,
            'status_label' => $b->status->getLabel(),
            'closed' => $b->status->isClosed(),
            'service' => $b->service->name,
            'for' => $b->principal_name ? strtok($b->principal_name, ' ') : strtok((string) $b->client?->name, ' '),
            'starts_at' => $b->starts_at->format('D j M, H:i'),
            'eta_minutes' => $eta,
            'operator' => $op ? [
                'first_name' => $op->displayName(),
                'call_sign' => $op->call_sign,
                'vehicle' => $lead->vehicle ? trim("{$lead->vehicle->colour} {$lead->vehicle->make} {$lead->vehicle->model}") : null,
                'registration' => $lead->vehicle?->registration,
            ] : null,
            'map' => [
                'pickup' => ['lat' => $b->pickup_lat, 'lng' => $b->pickup_lng],
                'dropoff' => $b->dropoff_lat ? ['lat' => $b->dropoff_lat, 'lng' => $b->dropoff_lng] : null,
                'stops' => [],
                'operators' => $tracking ? $assignments->filter(fn ($a) => $a->operator->last_lat !== null)->map(fn ($a) => ['lat' => $a->operator->last_lat, 'lng' => $a->operator->last_lng, 'label' => preg_replace('/^\D+/', '', (string) $a->operator->call_sign) ?: 'OP'])->values()->all() : [],
            ],
            'pickup_area' => $this->area($b->pickup_address),
            'dropoff_area' => $this->area($b->dropoff_address),
            'expires_at' => $share->expires_at->format('H:i'),
            'control_room' => settings('company.control_room_phone'),
        ];
    }

    /** Suburb-level description only; full addresses stay private. */
    private function area(?string $address): ?string
    {
        if (! $address) {
            return null;
        }
        $parts = array_map('trim', explode(',', $address));

        return count($parts) > 2 ? implode(', ', array_slice($parts, -3, 2)) : end($parts);
    }
}
