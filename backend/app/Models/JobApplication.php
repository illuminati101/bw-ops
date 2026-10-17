<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'position', 'years_experience', 'psira_number', 'psira_grade', 'firearm_competent', 'has_prdp', 'message', 'files', 'status', 'staff_notes'];

    protected function casts(): array
    {
        return ['files' => 'array', 'firearm_competent' => 'boolean', 'has_prdp' => 'boolean'];
    }
}
