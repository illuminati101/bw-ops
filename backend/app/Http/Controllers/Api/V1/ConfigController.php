<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Region;
use App\Services\Auth\OtpService;
use App\Services\Payments\PaymentService;

class ConfigController extends Controller
{
    public function __invoke(PaymentService $payments, OtpService $otp)
    {
        $region = Region::default();
        $features = collect(config('bwops.settings'))->keys()->filter(fn ($k) => str_starts_with($k, 'features.'))
            ->mapWithKeys(fn ($k) => [substr($k, 9) => (bool) settings($k)]);

        return response()->json([
            'brand' => [
                'name' => settings('company.trading_name'),
                'phone' => settings('company.phone'),
                'control_room_phone' => settings('company.control_room_phone'),
                'email' => settings('company.email'),
                'website' => settings('company.website'),
            ],
            'region' => ['id' => $region->id, 'name' => $region->name, 'lat' => $region->center_lat, 'lng' => $region->center_lng, 'radius_km' => $region->radius_km],
            'features' => $features,
            'booking' => [
                'notice_minutes' => (int) settings('booking.notice_minutes'),
                'notice_minutes_team' => (int) settings('booking.notice_minutes_team'),
                'max_days_ahead' => (int) settings('booking.max_days_ahead'),
                'reconfirm_hours' => (int) settings('booking.reconfirm_hours'),
            ],
            'cancellation' => [
                'tiers' => settings('cancellation.tiers'),
                'on_demand_free_minutes' => (int) settings('cancellation.on_demand_free_minutes'),
                'no_show_percent' => (float) settings('cancellation.no_show_percent'),
                'no_show_minimum' => (float) settings('cancellation.no_show_minimum'),
            ],
            'filters' => Feature::query()->clientFilterable()->get()->map(fn ($f) => [
                'id' => $f->id, 'label' => $f->labelForClients(), 'applies_to' => $f->applies_to, 'category' => $f->category,
                'description' => $f->client_description, 'surcharge' => $f->surcharge_type !== 'none' ? ['type' => $f->surcharge_type, 'amount' => (float) $f->surcharge_amount] : null,
            ]),
            'maps' => [
                'provider' => filled(config('bwops.integrations.google_maps_key')) ? 'google' : 'osm',
                'tile_url' => config('bwops.integrations.map_tiles'),
            ],
            'realtime' => ($rt = \App\Support\Realtime::publicConfig()) ? $rt + ['auth_endpoint' => url('/api/v1/broadcasting/auth')] : null,
            'payments' => ['gateway' => $payments->liveGatewayName(), 'record_only' => $payments->isRecordOnly()],
            'sms_test_mode' => $otp->revealCodes(),
            'legal' => [
                'terms_url' => url('/legal/terms'),
                'privacy_url' => url('/legal/privacy'),
                'cancellation_url' => url('/legal/cancellation'),
                'terms_version' => settings('legal.terms_version'),
            ],
            'apps' => [
                'min_version_client' => settings('apps.min_version_client'),
                'min_version_crew' => settings('apps.min_version_crew'),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
