<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use LogsActivity, SoftDeletes;

    public const CLASSES = [
        'sedan' => 'Executive sedan',
        'suv' => 'Luxury SUV',
        'van' => 'Executive van',
        'armoured_sedan' => 'Armoured sedan',
        'armoured_suv' => 'Armoured SUV',
    ];

    protected $fillable = ['make', 'model', 'year', 'colour', 'registration', 'vehicle_class', 'seats', 'is_armoured', 'ownership', 'owner_operator_id', 'status', 'photo_path', 'notes'];

    protected function casts(): array
    {
        return ['is_armoured' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'owner_operator_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_vehicle')->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function label(): string
    {
        return trim("{$this->colour} {$this->make} {$this->model}").' ('.$this->registration.')';
    }

    public function classLabel(): string
    {
        return self::CLASSES[$this->vehicle_class] ?? ucfirst($this->vehicle_class);
    }
}
