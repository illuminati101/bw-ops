<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPing extends Model
{
    public $timestamps = false;

    protected $fillable = ['operator_profile_id', 'booking_id', 'lat', 'lng', 'heading', 'speed', 'accuracy', 'recorded_at'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'recorded_at' => 'datetime'];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }
}
