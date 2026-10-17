<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SurchargeRule extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'kind', 'starts_at', 'ends_at', 'percent', 'is_active'];

    protected function casts(): array
    {
        return ['percent' => 'float', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
