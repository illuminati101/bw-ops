<?php

namespace App\Services\Maps;

use App\Models\Region;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address search, reverse geocoding and route distance.
 * Uses Google Maps Platform when a key is configured, otherwise OpenStreetMap (Nominatim / OSRM),
 * and finally a straight-line estimate so quoting never breaks.
 */
class MapsService
{
    public function hasGoogle(): bool
    {
        return filled(config('bwops.integrations.google_maps_key'));
    }

    /** @return array<int, array{label: string, address: string, lat: ?float, lng: ?float, place_id: ?string}> */
    public function search(string $query, ?Region $region = null, ?string $sessionToken = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }
        $region ??= Region::default();

        return Cache::remember('places:'.md5($query.$region->id.($this->hasGoogle() ? 'g' : 'o')), now()->addHours(12), function () use ($query, $region, $sessionToken) {
            try {
                return $this->hasGoogle() ? $this->googleSearch($query, $region, $sessionToken) : $this->osmSearch($query, $region);
            } catch (\Throwable $e) {
                Log::warning('Address search failed: '.$e->getMessage());

                return [];
            }
        });
    }

    public function placeDetails(string $placeId, ?string $sessionToken = null): ?array
    {
        if (! $this->hasGoogle()) {
            return null;
        }

        return Cache::remember('place:'.$placeId, now()->addDays(7), function () use ($placeId, $sessionToken) {
            $res = Http::timeout(8)->withHeaders([
                'X-Goog-Api-Key' => config('bwops.integrations.google_maps_key'),
                'X-Goog-FieldMask' => 'id,formattedAddress,location,displayName',
            ])->get("https://places.googleapis.com/v1/places/{$placeId}", array_filter(['sessionToken' => $sessionToken]));
            if (! $res->ok()) {
                return null;
            }
            $p = $res->json();

            return [
                'label' => $p['displayName']['text'] ?? $p['formattedAddress'],
                'address' => $p['formattedAddress'],
                'lat' => $p['location']['latitude'] ?? null,
                'lng' => $p['location']['longitude'] ?? null,
                'place_id' => $placeId,
            ];
        });
    }

    public function reverse(float $lat, float $lng): ?string
    {
        $key = 'rev:'.round($lat, 4).','.round($lng, 4);

        return Cache::remember($key, now()->addDays(7), function () use ($lat, $lng) {
            try {
                if ($this->hasGoogle()) {
                    $res = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'latlng' => "$lat,$lng", 'key' => config('bwops.integrations.google_maps_key'),
                    ]);

                    return $res->json('results.0.formatted_address');
                }
                $res = Http::timeout(8)->withHeaders(['User-Agent' => 'BlackAndWhiteOps/1.0 (bookings)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2']);

                return $res->json('display_name');
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Road distance and duration through the given points, in order.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{km: float, minutes: int, source: string}
     */
    public function route(array $points): array
    {
        $points = array_values(array_filter($points, fn ($p) => $p[0] !== null && $p[1] !== null));
        if (count($points) < 2) {
            return ['km' => 0.0, 'minutes' => 0, 'source' => 'none'];
        }

        $key = 'route:'.md5(json_encode($points));

        return Cache::remember($key, now()->addHours(6), function () use ($points) {
            try {
                if ($this->hasGoogle()) {
                    return $this->googleRoute($points);
                }

                return $this->osrmRoute($points);
            } catch (\Throwable $e) {
                Log::info('Route lookup fell back to estimate: '.$e->getMessage());
            }
            $km = 0.0;
            for ($i = 1; $i < count($points); $i++) {
                $km += Geo::roadKm($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
            }

            return ['km' => round($km, 1), 'minutes' => Geo::etaMinutes($km), 'source' => 'estimate'];
        });
    }

    private function googleSearch(string $query, Region $region, ?string $sessionToken): array
    {
        $res = Http::timeout(8)->withHeaders([
            'X-Goog-Api-Key' => config('bwops.integrations.google_maps_key'),
        ])->post('https://places.googleapis.com/v1/places:autocomplete', array_filter([
            'input' => $query,
            'includedRegionCodes' => ['za'],
            'locationBias' => ['circle' => [
                'center' => ['latitude' => $region->center_lat, 'longitude' => $region->center_lng],
                'radius' => min(50000, $region->radius_km * 1000),
            ]],
            'sessionToken' => $sessionToken,
        ]));
        $out = [];
        foreach ($res->json('suggestions', []) as $s) {
            $p = $s['placePrediction'] ?? null;
            if (! $p) {
                continue;
            }
            $out[] = [
                'label' => $p['structuredFormat']['mainText']['text'] ?? $p['text']['text'],
                'address' => $p['text']['text'],
                'lat' => null,
                'lng' => null,
                'place_id' => $p['placeId'],
            ];
        }

        return $out;
    }

    private function osmSearch(string $query, Region $region): array
    {
        [$south, $west, $north, $east] = $region->bounds ?: [
            $region->center_lat - 0.6, $region->center_lng - 0.6, $region->center_lat + 0.6, $region->center_lng + 0.6,
        ];
        $res = Http::timeout(8)->withHeaders(['User-Agent' => 'BlackAndWhiteOps/1.0 (bookings)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query, 'format' => 'jsonv2', 'countrycodes' => 'za', 'limit' => 6, 'addressdetails' => 0,
                'viewbox' => "$west,$north,$east,$south", 'bounded' => 1,
            ]);

        return collect($res->json() ?: [])->map(fn ($r) => [
            'label' => $r['name'] ?: explode(',', $r['display_name'])[0],
            'address' => $r['display_name'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lon'],
            'place_id' => null,
        ])->values()->all();
    }

    private function googleRoute(array $points): array
    {
        $origin = array_shift($points);
        $destination = array_pop($points);
        $wp = fn ($p) => ['location' => ['latLng' => ['latitude' => $p[0], 'longitude' => $p[1]]]];
        $res = Http::timeout(8)->withHeaders([
            'X-Goog-Api-Key' => config('bwops.integrations.google_maps_key'),
            'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
        ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
            'origin' => $wp($origin),
            'destination' => $wp($destination),
            'intermediates' => array_map($wp, $points),
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
        ]);
        $route = $res->json('routes.0');
        if (! $route) {
            throw new \RuntimeException('No Google route');
        }

        return [
            'km' => round($route['distanceMeters'] / 1000, 1),
            'minutes' => (int) ceil(((int) rtrim($route['duration'], 's')) / 60),
            'source' => 'google',
        ];
    }

    private function osrmRoute(array $points): array
    {
        $coords = implode(';', array_map(fn ($p) => $p[1].','.$p[0], $points));
        $res = Http::timeout(6)->get("https://router.project-osrm.org/route/v1/driving/{$coords}", ['overview' => 'false']);
        $route = $res->json('routes.0');
        if (! $route) {
            throw new \RuntimeException('No OSRM route');
        }

        return ['km' => round($route['distance'] / 1000, 1), 'minutes' => (int) ceil($route['duration'] / 60), 'source' => 'osm'];
    }
}
