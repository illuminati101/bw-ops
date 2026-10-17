<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\TripShare;
use Illuminate\Support\Str;

class TripShareService
{
    public function create(Booking $booking, ?string $label = null): TripShare
    {
        return $booking->shares()->create([
            'token' => Str::random(40),
            'label' => $label,
            'expires_at' => $booking->plannedEnd()->copy()->addHours(2)->max(now()->addHours(2)),
        ]);
    }

    /** One stable link per booking for the principal (sent by SMS when someone books for them). */
    public function forPrincipal(Booking $booking): TripShare
    {
        return $booking->shares()->where('label', 'principal')->first() ?? $this->create($booking, 'principal');
    }
}
