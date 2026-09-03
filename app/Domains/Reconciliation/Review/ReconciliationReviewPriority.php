<?php

namespace App\Domains\Reconciliation\Review;

use App\Models\PaymentAllocation;

final readonly class ReconciliationReviewPriority
{
    public function __construct(
        public PaymentAllocation $allocation,

        public int $score,

        public string $band,

        public string $bandLabel,

        public bool $actionable,

        public float $sourceOutstanding,

        public float $invoiceBalance,

        public float $effectiveApprovalAmount,

        public array $reasons,

        public array $warnings,

        public bool $humanAttributed,

        public bool $automatedCandidate,

        public int $invoiceSuggestionCount,

        public int $transactionSuggestionCount,
    ) {}
}
