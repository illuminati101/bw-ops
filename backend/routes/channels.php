<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('control-room', fn (User $user) => $user->isStaff());

Broadcast::channel('operator.{operatorProfileId}', function (User $user, int $operatorProfileId) {
    return $user->isStaff() || $user->operatorProfile?->id === $operatorProfileId;
});

Broadcast::channel('booking.{bookingId}', function (User $user, int $bookingId) {
    if ($user->isStaff()) {
        return true;
    }
    $booking = Booking::find($bookingId);
    if (! $booking) {
        return false;
    }
    if ($user->isOperator()) {
        return $booking->activeAssignments()->where('operator_profile_id', $user->operatorProfile?->id)->exists();
    }

    return Booking::query()->whereKey($bookingId)->visibleTo($user)->exists();
});
