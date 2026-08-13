<?php

namespace App\Models;

class BusinessDecisionOutcome extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'value' => 'float',
            'financial_result' => 'float',
            'decided_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
