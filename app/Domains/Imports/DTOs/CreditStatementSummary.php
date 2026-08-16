<?php

namespace App\Domains\Imports\DTOs;

use Carbon\CarbonImmutable;

class CreditStatementSummary
{
    public function __construct(
        public CarbonImmutable $statementFrom,

        public CarbonImmutable $statementTo,

        public ?float $openingBalance,

        public float $closingBalance,

        public ?float $minimumPayment,

        public ?CarbonImmutable $paymentDueAt,

        public ?float $creditLimit,

        public int $confidence
    ) {}

    public function toEvidenceArray(): array
    {
        return [
            'statement_from' => $this->statementFrom
                ->toDateString(),

            'statement_to' => $this->statementTo
                ->toDateString(),

            'opening_balance' => $this->openingBalance,

            'closing_balance' => $this->closingBalance,

            'minimum_payment' => $this->minimumPayment,

            'payment_due_at' => $this->paymentDueAt
                ?->toDateString(),

            'credit_limit' => $this->creditLimit,

            'confidence' => $this->confidence,
        ];
    }
}
