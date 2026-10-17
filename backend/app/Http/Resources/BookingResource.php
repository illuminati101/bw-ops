<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Services\Booking\BookingService;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A booking as the client (or their company admin) sees it. @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /** Set by the web portal, whose Livewire update requests don't carry the API route name. */
    public static bool $detailed = false;

    public function toArray(Request $request): array
    {
        $assignments = $this->activeAssignments()->with(['operator.user', 'vehicle'])->get();
        $lead = $assignments->firstWhere('role', 'driver') ?? $assignments->first();
        $eta = null;
        if ($lead && in_array($this->status, [BookingStatus::Assigned, BookingStatus::EnRoute], true) && $lead->operator->last_lat !== null) {
            $eta = Geo::etaMinutes(Geo::roadKm($lead->operator->last_lat, $lead->operator->last_lng, $this->pickup_lat, $this->pickup_lng));
        }
        $showPin = feature_enabled('arrival_pin') && ! $this->status->isClosed() && $assignments->isNotEmpty();
        $detailed = static::$detailed || $request->routeIs('*.bookings.show', '*.bookings.store', '*.bookings.*');

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'is_closed' => $this->status->isClosed(),
            'mode' => $this->mode,
            'structure' => $this->structure,
            'service' => ['id' => $this->service_id, 'name' => $this->service->name, 'slug' => $this->service->slug, 'icon' => $this->service->icon, 'includes_transport' => $this->service->includes_transport],
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->plannedEnd()->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'pickup' => ['address' => $this->pickup_address, 'lat' => $this->pickup_lat, 'lng' => $this->pickup_lng],
            'dropoff' => $this->dropoff_address ? ['address' => $this->dropoff_address, 'lat' => $this->dropoff_lat, 'lng' => $this->dropoff_lng] : null,
            'stops' => $this->stops->map(fn ($s) => ['address' => $s->address, 'lat' => $s->lat, 'lng' => $s->lng, 'wait_minutes' => $s->wait_minutes]),
            'shifts' => $this->shifts->map(fn ($s) => ['starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(), 'status' => $s->status]),
            'principal' => $this->principal_name ? ['name' => $this->principal_name, 'phone' => $this->principal_phone] : null,
            'passengers' => $this->passengers,
            'guards' => $this->guards_count,
            'vehicles' => $this->vehicles_count,
            'requirements' => \App\Models\Feature::query()->whereIn('id', $this->feature_ids ?? [])->get()->map->labelForClients()->values(),
            'notes' => $this->client_notes,
            'quote' => [
                'total' => $this->quoted_total,
                'final_total' => $this->final_total,
                'lines' => $this->quote_breakdown['lines'] ?? [],
                'surcharge' => $this->quote_breakdown['surcharge'] ?? null,
                'custom_note' => $this->quote_breakdown['custom_note'] ?? null,
                'overtime_rate' => $this->overtime_rate,
                'no_show_fee' => $this->quote_breakdown['no_show_fee'] ?? null,
                'hold_amount' => $this->quote_breakdown['hold_amount'] ?? 0,
                'vat_included' => $this->quote_breakdown['vat_included'] ?? 0,
                'awaiting_quote' => $this->status === BookingStatus::QuoteRequested,
                'awaiting_acceptance' => $this->status === BookingStatus::Quoted,
            ],
            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'card' => $this->paymentMethodRecord?->label(),
                'cancellation_fee' => $this->cancellation_fee,
            ],
            'arrival_pin' => $showPin ? $this->arrival_pin : null,
            'pin_verified' => (bool) $this->pin_verified_at,
            'operators' => OperatorPublicResource::collection($assignments),
            'eta_minutes' => $eta,
            'reconfirm' => [
                'required' => (bool) $this->reconfirm_due_at && ! $this->reconfirmed_at && ! $this->status->isClosed(),
                'due_at' => $this->reconfirm_due_at?->toIso8601String(),
                'done' => (bool) $this->reconfirmed_at,
            ],
            'can' => [
                'cancel' => $this->status->isCancellable(),
                'accept_quote' => $this->status === BookingStatus::Quoted,
                'chat' => $this->chatOpen(),
                'call' => feature_enabled('masked_calls') && $this->chatOpen() && app(\App\Services\Messaging\Channels::class)->twilioReady(),
                'share' => feature_enabled('trip_sharing') && ! $this->status->isClosed(),
                'rate' => feature_enabled('ratings') && $this->status === BookingStatus::Completed && ! $this->ratings()->where('rater_id', $request->user()?->id)->exists(),
                // SOS once the job is under way, or within the hour before it starts.
                'sos' => feature_enabled('sos') && (in_array($this->status, [BookingStatus::EnRoute, BookingStatus::Arrived, BookingStatus::InProgress], true)
                    || (in_array($this->status, [BookingStatus::Searching, BookingStatus::Assigned], true) && $this->starts_at->lte(now()->addHour()))),
            ],
            'timeline' => $detailed ? $this->events()->whereIn('type', ['booking.created', 'booking.approved', 'booking.quoted', 'booking.assigned', 'job.en_route', 'job.arrived', 'job.started', 'job.completed', 'booking.cancelled', 'booking.no_show', 'booking.reconfirmed'])
                ->get()->map(fn ($e) => ['type' => $e->type, 'summary' => $this->clientSummary($e), 'at' => $e->created_at->toIso8601String()]) : [],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function clientSummary($e): string
    {
        return match ($e->type) {
            'booking.created' => 'Booking requested',
            'booking.approved' => 'Confirmed by our team',
            'booking.quoted' => 'Quote sent',
            'booking.assigned' => 'Operator assigned',
            'job.en_route' => 'Operator on the way',
            'job.arrived' => 'Operator arrived',
            'job.started' => 'Job started',
            'job.completed' => 'Job completed',
            'booking.cancelled' => 'Cancelled',
            'booking.no_show' => 'Recorded as no-show',
            'booking.reconfirmed' => 'You reconfirmed',
            default => $e->summary,
        };
    }
}
