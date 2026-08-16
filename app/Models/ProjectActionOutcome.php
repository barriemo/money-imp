<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActionOutcome extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function projectAction(): BelongsTo
    {
        return $this->belongsTo(ProjectAction::class);
    }
}
