<?php

namespace App\Support;

class Geo
{
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Rough road distance from straight-line distance (Cape Town roads wind around the mountain). */
    public static function roadKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return round(self::distanceKm($lat1, $lng1, $lat2, $lng2) * 1.35, 1);
    }

    public static function etaMinutes(float $km, ?int $speedKmh = null): int
    {
        $speed = max(10, $speedKmh ?? (int) settings('dispatch.average_speed_kmh', 35));

        return (int) max(2, ceil($km / $speed * 60));
    }
}
