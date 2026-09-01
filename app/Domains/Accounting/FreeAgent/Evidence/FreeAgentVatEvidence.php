<?php

namespace App\Domains\Accounting\FreeAgent\Evidence;

final class FreeAgentVatEvidence
{
    public function __construct(
        public readonly string $periodEnd,
        public readonly string $label,
        public readonly float $amountDue,
        public readonly string $dueDate,
        public readonly string $status,
        public readonly string $filingStatus,
        public readonly int $confidence = 95,
    ) {}
}
