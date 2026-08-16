<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditStatementEvidence extends MoneyImpModel
{
    protected $table = 'credit_statement_evidence';

    protected function casts(): array
    {
        return [
            'statement_from' => 'date',
            'statement_to' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'minimum_payment' => 'decimal:2',
            'payment_due_at' => 'date',
            'credit_limit' => 'decimal:2',
            'verified' => 'boolean',
            'confidence' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(
            CreditFacility::class,
            'credit_facility_id'
        );
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(
            ImportBatch::class
        );
    }
}
