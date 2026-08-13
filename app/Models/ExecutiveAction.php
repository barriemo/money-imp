<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveAction extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'estimated_financial_impact' => 'decimal:2',
            'estimated_effort_minutes' => 'integer',
            'confidence' => 'integer',
            'urgency' => 'integer',
            'score' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'financial_result' => 'decimal:2',
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(
            Client::class
        );
    }
}
