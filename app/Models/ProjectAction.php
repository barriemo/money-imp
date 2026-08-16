<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
