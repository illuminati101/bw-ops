<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Page extends Model
{
    use HasDrafts, LogsActivity;

    protected $fillable = ['slug', 'title', 'meta_description', 'kind', 'blocks', 'body', 'show_in_footer', 'draft', 'is_published', 'published_at', 'publish_at', 'published_by'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'show_in_footer' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'slug', 'is_published'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
