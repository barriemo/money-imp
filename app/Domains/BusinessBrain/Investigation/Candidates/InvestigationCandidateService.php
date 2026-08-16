<?php

namespace App\Domains\BusinessBrain\Investigation\Candidates;

use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRisk;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationCandidateService
{
    public function __construct(
        private ClientLedgerRiskService $ledgerRisks
    ) {}

    /**
     * @return Collection<int, InvestigationCandidate>
     */
    public function current(): Collection
    {
        return $this->ledgerRisks
            ->current()
            ->filter(
                fn (ClientLedgerRisk $risk) => $this->isInvestigable(
                    $risk
                )
            )
            ->reject(
                fn (ClientLedgerRisk $risk) => $this->hasActiveCase(
                    $risk
                )
            )
            ->map(
                fn (ClientLedgerRisk $risk) => $this->candidate(
                    $risk
                )
            )
            ->sortByDesc(
                'priority'
            )
            ->values();
    }

    private function isInvestigable(
        ClientLedgerRisk $risk
    ): bool {
        return
            $risk->classification
                !== 'ledger_reconciled'
            && $risk->priority > 0;
    }

    private function hasActiveCase(
        ClientLedgerRisk $risk
    ): bool {
        return InvestigationCase::query()
            ->where(
                'type',
                'client_ledger'
            )
            ->where(
                'subject_type',
                'client'
            )
            ->where(
                'subject_id',
                $risk->clientId
            )
            ->whereIn(
                'status',
                [
                    'open',
                    'testing',
                    'waiting',
                ]
            )
            ->exists();
    }

    private function candidate(
        ClientLedgerRisk $risk
    ): InvestigationCandidate {
        return new InvestigationCandidate(
            type: 'client_ledger',

            subjectType: 'client',

            subjectId: $risk->clientId,

            subjectName: $risk->clientName,

            title: sprintf(
                'Investigate ledger anomaly for %s',
                $risk->clientName
            ),

            question: 'Why does the client ledger not reconcile?',

            classification: $risk->classification,

            priority: $risk->priority,

            confidence: $risk->confidence,

            reasons: $risk->reasons,

            actions: $risk->actions,

            metadata: [
                'ledger_difference' => $risk->difference,

                'cash_received' => $risk->cashReceived,

                'invoice_value' => $risk->invoiceValue,

                'source' => 'client_ledger_risk',
            ]
        );
    }
}
