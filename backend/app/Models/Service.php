<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use HasDrafts, LogsActivity, SoftDeletes;

    public const CATEGORIES = [
        'transport' => 'Transport only',
        'protection' => 'Protection only',
        'transport_protection' => 'Transport + protection',
    ];

    protected $fillable = [
        'name', 'slug', 'category', 'tagline', 'summary', 'description', 'highlights', 'ideal_for',
        'includes_transport', 'includes_protection', 'default_guards', 'default_vehicles', 'allows_on_demand', 'allows_advance',
        'structure', 'min_notice_minutes', 'min_duration_minutes', 'approval_rule', 'custom_quote', 'default_feature_ids',
        'icon', 'image_path', 'sort',
        'draft', 'is_published', 'published_at', 'publish_at', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'default_feature_ids' => 'array',
            'includes_transport' => 'boolean',
            'includes_protection' => 'boolean',
            'allows_on_demand' => 'boolean',
            'allows_advance' => 'boolean',
            'custom_quote' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(Extra::class, 'extra_service');
    }

    public function rateCardFor(Region $region, bool $published = true): ?RateCard
    {
        $q = $this->rateCards()->where('region_id', $region->id);
        if ($published) {
            $q->where('is_published', true);
        }

        return $q->first();
    }

    /** Whether this service is bookable given the global feature switches. */
    public function isAvailable(): bool
    {
        return match ($this->structure) {
            'team' => feature_enabled('team_bookings'),
            'multi_day' => feature_enabled('multi_day'),
            default => true,
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
