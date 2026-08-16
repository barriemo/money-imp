<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'investigation_case_id',
        'type',
        'actor_type',
        'description',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function investigationCase(): BelongsTo
    {
        return $this->belongsTo(
            InvestigationCase::class
        );
    }
}
