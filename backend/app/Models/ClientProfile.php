<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientProfile extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id', 'code_name', 'verification_status', 'id_document_path', 'selfie_path', 'id_number_last4',
        'verification_submitted_at', 'verified_at', 'verified_by', 'verification_notes', 'trust_level',
        'eft_approved', 'requires_approval', 'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'verification_submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'eft_approved' => 'boolean',
            'requires_approval' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['verification_status', 'trust_level', 'eft_approved', 'requires_approval'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isTrusted(): bool
    {
        return $this->trust_level === 'trusted';
    }
}
