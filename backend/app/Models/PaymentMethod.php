<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'gateway', 'authorization_code', 'signature', 'email', 'brand', 'last4', 'exp_month', 'exp_year', 'bank', 'reusable', 'is_default', 'verified_at'];

    protected $hidden = ['authorization_code', 'signature'];

    protected function casts(): array
    {
        return ['authorization_code' => 'encrypted', 'reusable' => 'boolean', 'is_default' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        if ($this->gateway === 'manual') {
            return 'Card on file (record only)';
        }

        return trim(ucfirst((string) $this->brand).' •••• '.$this->last4).($this->exp_month ? " · {$this->exp_month}/".substr((string) $this->exp_year, -2) : '');
    }

    public function isExpired(): bool
    {
        if (! $this->exp_year || ! $this->exp_month) {
            return false;
        }

        return now()->startOfMonth()->gt(\Carbon\Carbon::create((int) $this->exp_year, (int) $this->exp_month, 1));
    }
}
