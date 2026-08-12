<?php

namespace App\Domains\BusinessBrain\Providers;

use App\Domains\BusinessBrain\BusinessObservation;
use App\Domains\BusinessBrain\Contracts\BusinessObservationProvider;
use App\Domains\Evidence\EvidenceItem;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Illuminate\Support\Collection;

class FinancialObservationProvider implements BusinessObservationProvider
{
    public function __construct(
        private FinancialTruthService $financialTruth
    ) {}

    public function observations(): Collection
    {
        $truth = $this->financialTruth->build();

        $bankConfidence =
            (int) (
                $truth['confidence']['bank_balances']
                ?? 0
            );

        $liabilityConfidence =
            (int) (
                $truth['confidence']['liabilities']
                ?? 0
            );

        $receivableConfidence =
            (int) (
                $truth['confidence']['receivables']
                ?? 0
            );

        $observations = collect();

        $observations->push(
            (new BusinessObservation(
                type: 'cash_position',

                summary: $bankConfidence === 100
                        ? 'Bank and card balances are fully verified.'
                        : 'Bank and card balances are not fully verified.',

                confidence: $bankConfidence,

                data: [
                    'available' => $bankConfidence > 0
                            ? (float) (
                                $truth['cash']['available']
                                ?? 0
                            )
                            : null,

                    'credit_card_debt' => $bankConfidence > 0
                            ? (float) (
                                $truth['cash']['credit_card_debt']
                                ?? 0
                            )
                            : null,

                    'net_position' => $bankConfidence > 0
                        && $liabilityConfidence > 0
                            ? (float) (
                                $truth['cash']['net_position']
                                ?? 0
                            )
                            : null,
                ]
            ))->addEvidence(
                new EvidenceItem(
                    type: 'financial_truth',
                    source: 'system_inference',
                    summary: 'Financial Truth bank balance assessment.',
                    confidence: $bankConfidence,
                    verified: $bankConfidence === 100
                )
            )
        );

        $observations->push(
            (new BusinessObservation(
                type: 'liabilities',

                summary: $liabilityConfidence === 100
                        ? 'Known liabilities are fully verified.'
                        : 'Known liabilities are not fully verified.',

                confidence: $liabilityConfidence,

                data: [
                    'total' => $liabilityConfidence > 0
                            ? (float) (
                                $truth['liabilities']['total']
                                ?? 0
                            )
                            : null,
                ]
            ))->addEvidence(
                new EvidenceItem(
                    type: 'financial_truth',
                    source: 'system_inference',
                    summary: 'Financial Truth liability assessment.',
                    confidence: $liabilityConfidence,
                    verified: $liabilityConfidence === 100
                )
            )
        );

        $observations->push(
            (new BusinessObservation(
                type: 'receivables',

                summary: $receivableConfidence === 100
                        ? 'Receivables are verified as collectible.'
                        : 'Ledger receivables are not yet verified as collectible cash.',

                confidence: $receivableConfidence,

                data: [
                    'ledger_outstanding' => (float) (
                        $truth['receivables']['ledger_outstanding']
                        ?? 0
                    ),

                    'verified_collectible' => $truth['receivables']['verified_collectible']
                        ?? null,
                ]
            ))->addEvidence(
                new EvidenceItem(
                    type: 'financial_truth',
                    source: 'system_inference',
                    summary: 'Financial Truth receivables assessment.',
                    confidence: $receivableConfidence,
                    verified: $receivableConfidence === 100
                )
            )
        );

        return $observations;
    }
}
