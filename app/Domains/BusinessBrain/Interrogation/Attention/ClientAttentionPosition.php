<?php

namespace App\Domains\BusinessBrain\Interrogation\Attention;

class ClientAttentionPosition
{
    public function __construct(
        public string $clientId,

        public string $client,

        public float $outstanding,

        public float $overdue,

        public int $unmatchedTransactions,

        public int $highPriorityFindings,

        public int $highestCharliePriority,

        public ?\DateTimeInterface $lastInvoiceDate,

        public ?int $daysSinceLastInvoice,

        public bool $billingDormant,

        public array $reasons,

        public int $score
    ) {}
}
