<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OperatorProfile extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id', 'region_id', 'call_sign', 'employment_type', 'roles', 'gender', 'psira_number', 'psira_grade',
        'hired_on', 'status', 'is_online', 'online_since', 'last_lat', 'last_lng', 'last_heading', 'last_location_at',
        'current_vehicle_id', 'pay_rate_hourly', 'pay_rate_km', 'rating_avg', 'rating_count', 'bank_details', 'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'is_online' => 'boolean',
            'online_since' => 'datetime',
            'last_location_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'pay_rate_hourly' => 'decimal:2',
            'pay_rate_km' => 'decimal:2',
            'bank_details' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['call_sign', 'employment_type', 'roles', 'status', 'psira_number', 'psira_grade', 'current_vehicle_id', 'pay_rate_hourly', 'pay_rate_km'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_operator')->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    public function ownedVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'owner_operator_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function liveAssignments(): HasMany
    {
        return $this->assignments()->whereIn('status', array_map(fn ($s) => $s->value, [AssignmentStatus::EnRoute, AssignmentStatus::Arrived, AssignmentStatus::OnJob]));
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DispatchOffer::class);
    }

    public function rosterShifts(): HasMany
    {
        return $this->hasMany(RosterShift::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, explode(',', (string) $this->roles), true);
    }

    public function isContractor(): bool
    {
        return $this->employment_type === 'contractor';
    }

    public function displayName(): string
    {
        return $this->user?->firstName() ?? 'Operator';
    }

    public function label(): string
    {
        return trim(($this->user?->name ?? '').($this->call_sign ? ' ('.$this->call_sign.')' : ''));
    }
}
