<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Maps\MapsService;
use Illuminate\Http\Request;

class PlacesController extends Controller
{
    public function search(Request $request, MapsService $maps)
    {
        $data = $request->validate(['q' => 'required|string|min:3|max:120', 'session' => 'nullable|string|max:64']);

        return ['results' => $maps->search($data['q'], null, $data['session'] ?? null)];
    }

    public function details(Request $request, MapsService $maps)
    {
        $data = $request->validate(['place_id' => 'required|string|max:300', 'session' => 'nullable|string|max:64']);

        return ['place' => $maps->placeDetails($data['place_id'], $data['session'] ?? null)];
    }

    public function reverse(Request $request, MapsService $maps)
    {
        $data = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);

        return ['address' => $maps->reverse((float) $data['lat'], (float) $data['lng'])];
    }
}
