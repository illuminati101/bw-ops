<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payout extends Model
{
    use LogsActivity;

    protected $fillable = ['operator_profile_id', 'period_start', 'period_end', 'jobs_count', 'total', 'status', 'paid_at', 'reference', 'paid_by'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'datetime', 'total' => 'float'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total', 'paid_at', 'reference'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }
}
