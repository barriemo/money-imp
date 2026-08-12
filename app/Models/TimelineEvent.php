<?php

namespace App\Models;

class TimelineEvent extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'confidence_before' => 'integer',
            'confidence_after' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
