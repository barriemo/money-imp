<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends MoneyImpModel
{
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'raw_payload' => 'array',
            'normalised_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ImportBatch::class,
            'import_batch_id'
        );
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            BankTransaction::class,
            'bank_transaction_id'
        );
    }
}
