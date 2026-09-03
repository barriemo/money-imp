<?php

namespace App\Domains\BusinessBrain\Signals;

final class CeoSignalFinding
{
    public function __construct(
        public readonly string $state,

        public readonly string $searchState,

        public readonly string $subjectName,

        public readonly string $headline,

        public readonly string $summary,

        public readonly string $nextStep,

        public readonly string $truthBoundary,

        public readonly float $accountingOutstanding,

        public readonly int $invoiceCount,

        public readonly bool $bankDateSpanCoversInvoices,

        public readonly int $supportedCandidateCount,
    ) {}

    public function toArray(): array
    {
        return [
            'state' => $this->state,

            'search_state' => $this->searchState,

            'subject_name' => $this->subjectName,

            'headline' => $this->headline,

            'summary' => $this->summary,

            'next_step' => $this->nextStep,

            'truth_boundary' => $this->truthBoundary,

            'accounting_outstanding' => $this->accountingOutstanding,

            'invoice_count' => $this->invoiceCount,

            'bank_date_span_covers_invoices' => $this->bankDateSpanCoversInvoices,

            'supported_candidate_count' => $this->supportedCandidateCount,
        ];
    }
}
