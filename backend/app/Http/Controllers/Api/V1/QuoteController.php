<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __invoke(Request $request, BookingService $bookings)
    {
        $request->validate(BookingController::rules());
        $result = $bookings->quote($request->all(), $request->user('sanctum'));

        return [
            'quote' => $result['quote']->toArray(),
            'route' => $result['route'],
            'structure' => $result['structure'],
            'duration_minutes' => $result['duration_minutes'],
            'minimum_minutes' => $result['minimum_minutes'],
            'starts_at' => $result['starts_at']->toIso8601String(),
            'shifts' => $result['shifts'],
        ];
    }
}
