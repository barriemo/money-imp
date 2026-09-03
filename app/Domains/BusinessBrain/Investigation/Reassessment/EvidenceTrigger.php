<?php

namespace App\Domains\BusinessBrain\Investigation\Reassessment;

use Illuminate\Support\Str;

class EvidenceTrigger
{
    public function __construct(
        public string $domain,

        public string $type,

        public array $metadata = [],

        public ?string $correlationId = null
    ) {
        $this->correlationId ??=
            (string) Str::uuid();
    }

    public function description(): string
    {
        return match ([$this->domain, $this->type]) {
            ['bank', 'transactions_imported'] => 'New bank transactions were imported.',

            ['bank', 'bank_transactions_changed'] => 'FreeAgent bank transaction evidence changed.',

            ['accounting', 'invoices_changed'] => 'FreeAgent invoice evidence changed.',

            ['bank', 'client_payment_attribution_changed'] => 'Client payment attribution evidence changed.',

            ['bank', 'payment_allocation_approved'] => 'A client payment allocation was approved.',

            ['bank', 'payment_allocation_rejected'] => 'A client payment allocation was rejected.',

            ['bank', 'bank_transaction_classification_changed'] => 'A bank transaction classification changed.',

            default => sprintf(
                '%s evidence changed: %s.',
                ucfirst($this->domain),
                str_replace(
                    '_',
                    ' ',
                    $this->type
                )
            ),
        };
    }
}
