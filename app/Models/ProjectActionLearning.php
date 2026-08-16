<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActionLearning extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_action_id',
        'type',
        'summary',
        'impact',
        'confidence',
        'learned_at',
    ];

    protected $casts = [
        'learned_at' => 'datetime',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(
            ProjectAction::class,
            'project_action_id'
        );
    }
}
