<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Faq extends Model
{
    use HasDrafts, LogsActivity;

    protected $fillable = ['category', 'question', 'answer', 'sort', 'draft', 'is_published', 'published_at', 'publish_at', 'published_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['question', 'is_published'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
