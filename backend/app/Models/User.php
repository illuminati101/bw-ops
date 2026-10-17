<?php

namespace App\Models;

use App\Enums\UserType;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasApiTokens, HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'type', 'status', 'password', 'avatar_path', 'marketing_opt_in',
        'accepted_terms_at', 'accepted_terms_version', 'email_verified_at', 'phone_verified_at', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'safety_pin_hash', 'duress_pin_hash'];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'phone', 'type', 'status'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->type === UserType::Staff && $this->status === 'active';
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isOperator(): bool
    {
        return $this->type === UserType::Operator;
    }

    public function isClient(): bool
    {
        return $this->type === UserType::Client;
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function operatorProfile(): HasOne
    {
        return $this->hasOne(OperatorProfile::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->orderByDesc('is_default')->latest('id')->first();
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(Strike::class);
    }

    public function activeStrikesCount(): int
    {
        return $this->strikes()->whereNull('voided_at')->count();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function onCallShifts(): HasMany
    {
        return $this->hasMany(OnCallShift::class);
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    /** What operators may see: a code name if set, otherwise the first name. */
    public function displayNameForOperators(): string
    {
        return $this->clientProfile?->code_name ?: $this->firstName();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email ?? $this->name;
    }
}
