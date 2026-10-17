<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence' => 'array',
            'feature_ids' => 'array',
            'quote_breakdown' => 'array',
            'pickup_lat' => 'float', 'pickup_lng' => 'float', 'dropoff_lat' => 'float', 'dropoff_lng' => 'float',
            'estimated_km' => 'float', 'actual_km' => 'float',
            'quoted_total' => 'float', 'final_total' => 'float', 'cancellation_fee' => 'float', 'overtime_rate' => 'float',
            'pin_verified_at' => 'datetime', 'approved_at' => 'datetime', 'quote_sent_at' => 'datetime', 'quote_accepted_at' => 'datetime',
            'reconfirm_due_at' => 'datetime', 'reconfirmed_at' => 'datetime', 'reconfirm_alerted_at' => 'datetime',
            'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'no_show_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'starts_at', 'quoted_total', 'final_total', 'payment_status', 'cancellation_fee', 'internal_notes'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'BW-'.strtoupper(Str::random(6));
            $ref = strtr($ref, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'W', 'L' => 'K']);
        } while (static::withTrashed()->where('reference', $ref)->exists());

        return $ref;
    }

    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function preferredOperator(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'preferred_operator_id');
    }

    public function paymentMethodRecord(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id')->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(BookingStop::class)->orderBy('sequence');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(BookingShift::class)->orderBy('starts_at');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNotIn('status', [AssignmentStatus::Declined->value, AssignmentStatus::Removed->value]);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DispatchOffer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->latest('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TripShare::class);
    }

    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::open()));
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::active()));
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        $companyIds = $user->companies()->wherePivotIn('role', ['admin'])->pluck('companies.id');

        return $q->where(function ($w) use ($user, $companyIds) {
            $w->where('client_id', $user->id)->orWhere('booked_by_id', $user->id);
            if ($companyIds->isNotEmpty()) {
                $w->orWhereIn('company_id', $companyIds);
            }
        });
    }

    public function isOnDemand(): bool
    {
        return $this->mode === 'on_demand';
    }

    public function plannedEnd(): \Carbon\CarbonInterface
    {
        return $this->ends_at ?? $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function principalDisplayName(): string
    {
        return $this->principal_name ?: $this->client?->displayNameForOperators() ?? 'Client';
    }

    public function logEvent(string $type, string $summary, array $data = [], ?int $actorId = null): BookingEvent
    {
        return $this->events()->create([
            'type' => $type,
            'summary' => $summary,
            'data' => $data ?: null,
            'actor_id' => $actorId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function chatOpen(): bool
    {
        return feature_enabled('chat') && ! $this->status->isClosed() && $this->activeAssignments()->exists();
    }
}
