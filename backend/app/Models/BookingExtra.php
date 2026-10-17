<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExtra extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'extra_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
