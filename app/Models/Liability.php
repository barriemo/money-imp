<?php

namespace App\Models;

class Liability extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'verified' => 'boolean',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }
}
