<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Feature extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'slug', 'applies_to', 'category', 'icon', 'client_label', 'client_description', 'client_filterable', 'surcharge_amount', 'surcharge_type', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['client_filterable' => 'boolean', 'is_active' => 'boolean', 'surcharge_amount' => 'decimal:2'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(OperatorProfile::class, 'feature_operator')->withTimestamps();
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'feature_vehicle')->withTimestamps();
    }

    /** Documents an operator must hold (valid) for this feature to count. */
    public function requiredDocumentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'document_type_feature');
    }

    public function scopeClientFilterable(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('client_filterable', true)->orderBy('sort');
    }

    public function labelForClients(): string
    {
        return $this->client_label ?: $this->name;
    }
}
