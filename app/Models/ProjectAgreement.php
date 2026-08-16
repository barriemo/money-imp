<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAgreement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'included_deliverables' => 'array',
        'approved_at' => 'datetime',
    ];
}
