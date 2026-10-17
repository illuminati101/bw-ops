<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OperatorProfile;
use App\Models\Region;
use App\Models\SosAlert;

class ControlRoomController extends Controller
{
    public function data()
    {
        abort_unless(auth()->user()->can('control_room.view'), 403);
        $live = Booking::query()->whereIn('status', array_map(fn ($s) => $s->value, [...BookingStatus::active(), BookingStatus::PendingApproval]))
            ->where(fn ($q) => $q->where('mode', 'on_demand')->orWhere('starts_at', '<=', now()->addHours(3)))
            ->with(['service', 'client', 'activeAssignments.operator'])->get();
        $busyOps = $live->flatMap(fn ($b) => $b->activeAssignments->whereIn('status', [AssignmentStatus::EnRoute, AssignmentStatus::Arrived, AssignmentStatus::OnJob])->pluck('operator_profile_id'))->unique()->all();
        $region = Region::default();

        return response()->json([
            'center' => [$region->center_lat, $region->center_lng],
            'operators' => OperatorProfile::query()->active()->with(['user', 'currentVehicle'])->whereNotNull('last_lat')
                ->where(fn ($q) => $q->where('is_online', true)->orWhere('last_location_at', '>=', now()->subHours(2)))->get()
                ->map(fn ($o) => [
                    'id' => $o->id, 'call_sign' => $o->call_sign ?? '?', 'name' => $o->user?->name, 'lat' => $o->last_lat, 'lng' => $o->last_lng,
                    'online' => $o->is_online, 'busy' => in_array($o->id, $busyOps, true), 'vehicle' => $o->currentVehicle?->label(),
                    'stale' => ! $o->last_location_at || $o->last_location_at->lt(now()->subMinutes((int) settings('dispatch.location_stale_minutes'))),
                    'seen' => $o->last_location_at?->diffForHumans(),
                ]),
            'bookings' => $live->map(fn ($b) => [
                'id' => $b->id, 'reference' => $b->reference, 'status' => $b->status->value, 'status_label' => $b->status->getLabel(), 'service' => $b->service->name,
                'client' => $b->client->name, 'lat' => $b->pickup_lat, 'lng' => $b->pickup_lng, 'pickup' => $b->pickup_address,
                'starts_at' => $b->starts_at->format('H:i'), 'operators' => $b->activeAssignments->map(fn ($a) => $a->operator->call_sign)->implode(', '),
                'url' => \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $b]),
            ]),
            'sos' => SosAlert::query()->whereIn('status', ['open', 'acknowledged'])->with(['user', 'booking'])->get()->map(fn ($s) => [
                'id' => $s->id, 'who' => $s->user?->name, 'role' => $s->raised_by_role, 'lat' => $s->lat, 'lng' => $s->lng, 'status' => $s->status,
                'since' => $s->created_at->diffForHumans(), 'booking' => $s->booking?->reference,
            ]),
            'at' => now()->format('H:i:s'),
        ]);
    }
}
