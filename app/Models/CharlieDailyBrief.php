<?php

namespace App\Models;

class CharlieDailyBrief extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'brief_date' => 'date',

            'client_count' => 'integer',

            'attention_count' => 'integer',

            'new_finding_count' => 'integer',

            'resolved_finding_count' => 'integer',

            'estimated_monthly_value' => 'float',

            'summary' => 'array',

            'metadata' => 'array',
        ];
    }
}
