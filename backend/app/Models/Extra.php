<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Extra extends Model
{
    use HasDrafts, LogsActivity;

    protected $fillable = ['name', 'slug', 'description', 'pricing', 'amount', 'requires_vehicle', 'feature_id', 'sort', 'draft', 'is_published', 'published_at', 'publish_at', 'published_by'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'requires_vehicle' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'extra_service');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
