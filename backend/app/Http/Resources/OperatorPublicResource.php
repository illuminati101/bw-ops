<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What a client may see about their operator. @mixin \App\Models\BookingAssignment */
class OperatorPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $op = $this->operator;

        return [
            'assignment_id' => $this->id,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'operator_id' => $op->id,
            'first_name' => $op->displayName(),
            'call_sign' => $op->call_sign,
            'photo_url' => $op->user?->avatar_path ? asset('storage/'.$op->user->avatar_path) : null,
            'vehicle' => $this->vehicle ? [
                'label' => trim("{$this->vehicle->colour} {$this->vehicle->make} {$this->vehicle->model}"),
                'registration' => $this->vehicle->registration,
                'class' => $this->vehicle->classLabel(),
                'armoured' => $this->vehicle->is_armoured,
            ] : null,
            'location' => in_array($this->status->value, ['accepted', 'en_route', 'arrived', 'on_job'], true) && $op->last_lat !== null ? [
                'lat' => $op->last_lat, 'lng' => $op->last_lng, 'heading' => $op->last_heading,
                'at' => $op->last_location_at?->toIso8601String(),
            ] : null,
        ];
    }
}
