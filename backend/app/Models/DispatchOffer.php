<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchOffer extends Model
{
    protected $fillable = ['booking_id', 'operator_profile_id', 'role', 'distance_km', 'offered_at', 'expires_at', 'status', 'responded_at'];

    protected function casts(): array
    {
        return ['offered_at' => 'datetime', 'expires_at' => 'datetime', 'responded_at' => 'datetime', 'distance_km' => 'float'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
