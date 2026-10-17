<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use LogsActivity;

    protected $fillable = ['number', 'company_id', 'user_id', 'period_start', 'period_end', 'subtotal', 'vat', 'total', 'status', 'due_on', 'sent_at', 'paid_at', 'payment_reference', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'due_on' => 'date', 'sent_at' => 'datetime', 'paid_at' => 'datetime', 'subtotal' => 'float', 'vat' => 'float', 'total' => 'float'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total', 'paid_at', 'payment_reference'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function billTo(): string
    {
        return $this->company?->name ?? $this->user?->name ?? '—';
    }
}
