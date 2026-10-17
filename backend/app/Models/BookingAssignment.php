<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BookingAssignment extends Model
{
    use LogsActivity;

    protected $fillable = ['booking_id', 'booking_shift_id', 'operator_profile_id', 'vehicle_id', 'role', 'status', 'responded_at', 'en_route_at', 'arrived_at', 'started_at', 'finished_at', 'payout_amount', 'payout_id'];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'responded_at' => 'datetime', 'en_route_at' => 'datetime', 'arrived_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
            'payout_amount' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['operator_profile_id', 'vehicle_id', 'role', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(BookingShift::class, 'booking_shift_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'driver' => 'Chauffeur',
            'guard' => 'Protection officer',
            'lead' => 'Detail lead',
            default => ucfirst($this->role),
        };
    }
}
