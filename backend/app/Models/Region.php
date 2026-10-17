<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['name', 'slug', 'center_lat', 'center_lng', 'radius_km', 'bounds', 'is_active'];

    protected function casts(): array
    {
        return ['bounds' => 'array', 'is_active' => 'boolean', 'center_lat' => 'float', 'center_lng' => 'float'];
    }

    public static function default(): self
    {
        return static::query()->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    public function contains(float $lat, float $lng): bool
    {
        return \App\Support\Geo::distanceKm($this->center_lat, $this->center_lng, $lat, $lng) <= $this->radius_km;
    }
}
