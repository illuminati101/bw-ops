<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlert extends Model
{
    protected $fillable = ['user_id', 'booking_id', 'raised_by_role', 'lat', 'lng', 'status', 'escalation_level', 'last_escalated_at', 'acknowledged_by', 'acknowledged_at', 'resolved_at', 'notes'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'last_escalated_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
