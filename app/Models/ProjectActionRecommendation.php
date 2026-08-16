<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectActionRecommendation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'integer',
    ];

    public function action()
    {
        return $this->belongsTo(
            ProjectAction::class,
            'project_action_id'
        );
    }
}
