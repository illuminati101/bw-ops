<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use LogsActivity;

    protected $fillable = ['documentable_type', 'documentable_id', 'document_type_id', 'number', 'file_path', 'issued_on', 'expires_on', 'status', 'reviewed_by', 'reviewed_at', 'reminder_sent_at', 'notes'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'reviewed_at' => 'datetime', 'reminder_sent_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['document_type_id', 'number', 'expires_on', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isValid(?\DateTimeInterface $on = null): bool
    {
        $on ??= now();

        return $this->status === 'approved' && ($this->expires_on === null || $this->expires_on->endOfDay()->gte($on));
    }

    public function scopeValid(Builder $q): Builder
    {
        return $q->where('status', 'approved')->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->where('status', 'approved')->whereDate('expires_on', '>=', today())->whereDate('expires_on', '<=', today()->addDays($days));
    }
}
