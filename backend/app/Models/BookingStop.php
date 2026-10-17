<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStop extends Model
{
    protected $fillable = ['booking_id', 'sequence', 'address', 'lat', 'lng', 'wait_minutes', 'arrived_at'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'arrived_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
