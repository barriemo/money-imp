<?php

namespace App\Models;

class BusinessObservationSnapshotRecord extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'observations' => 'array',
        ];
    }
}
