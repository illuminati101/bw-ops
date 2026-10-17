<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = ['name', 'slug', 'applies_to', 'requires_expiry', 'required_for_all_jobs', 'required_for_driving', 'reminder_days', 'description'];

    protected function casts(): array
    {
        return ['requires_expiry' => 'boolean', 'required_for_all_jobs' => 'boolean', 'required_for_driving' => 'boolean'];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'document_type_feature');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
