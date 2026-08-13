<?php

namespace App\Models;

class BusinessMemoryEvent extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'confidence' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
