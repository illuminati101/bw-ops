<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripShare extends Model
{
    protected $fillable = ['booking_id', 'token', 'label', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture() && ! $this->booking->status->isClosed();
    }
}
