<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    protected $fillable = ['channel', 'to', 'user_id', 'subject', 'body', 'driver', 'status', 'provider_id', 'error', 'sensitive'];

    protected function casts(): array
    {
        return ['sensitive' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
