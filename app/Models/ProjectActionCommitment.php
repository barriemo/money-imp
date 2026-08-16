<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActionCommitment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_action_id',
        'owner',
        'status',
        'due_date',
        'committed_at',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'committed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(
            ProjectAction::class,
            'project_action_id'
        );
    }
}
