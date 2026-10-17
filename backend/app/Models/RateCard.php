<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RateCard extends Model
{
    use HasDrafts, LogsActivity;

    protected $fillable = [
        'service_id', 'region_id', 'callout_fee', 'per_km', 'per_hour', 'per_extra_guard_hour', 'minimum_charge',
        'included_km', 'included_wait_minutes', 'overtime_per_hour',
        'draft', 'is_published', 'published_at', 'publish_at', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'callout_fee' => 'float', 'per_km' => 'float', 'per_hour' => 'float', 'per_extra_guard_hour' => 'float',
            'minimum_charge' => 'float', 'overtime_per_hour' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function draftableAttributes(): array
    {
        return ['callout_fee', 'per_km', 'per_hour', 'per_extra_guard_hour', 'minimum_charge', 'included_km', 'included_wait_minutes', 'overtime_per_hour'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function overtimeRate(): float
    {
        return (float) ($this->overtime_per_hour ?: $this->per_hour);
    }
}
