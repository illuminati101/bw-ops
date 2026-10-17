<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Strike extends Model
{
    protected $fillable = ['user_id', 'booking_id', 'reason', 'voided_at', 'voided_by'];

    protected function casts(): array
    {
        return ['voided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
