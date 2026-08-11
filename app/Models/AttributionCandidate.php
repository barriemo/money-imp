<?php

namespace App\Models;

class AttributionCandidate extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }
}
