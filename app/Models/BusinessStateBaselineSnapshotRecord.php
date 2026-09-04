<?php

namespace App\Models;

class BusinessStateBaselineSnapshotRecord extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'as_of' => 'immutable_datetime',
            'metrics' => 'array',
        ];
    }
}
