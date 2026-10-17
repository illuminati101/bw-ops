<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingShift extends Model
{
    protected $fillable = ['booking_id', 'starts_at', 'ends_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function label(): string
    {
        return $this->starts_at->format('D j M H:i').' – '.$this->ends_at->format($this->ends_at->isSameDay($this->starts_at) ? 'H:i' : 'D j M H:i');
    }
}
