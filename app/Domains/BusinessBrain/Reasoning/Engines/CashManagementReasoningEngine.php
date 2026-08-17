<?php

namespace App\Domains\BusinessBrain\Reasoning\Engines;

use App\Domains\BusinessBrain\CashTruth\CashTruthService;
use App\Domains\BusinessBrain\Finance\DTOs\CashRiskAssessment;
use App\Domains\BusinessBrain\Finance\Services\CashManagementService;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use Illuminate\Support\Collection;

class CashManagementReasoningEngine
{
    public function __construct(
        private CashManagementService $cashManagement,

        private CashTruthService $cashTruth
    ) {}

    public function current(): Collection
    {
        $truth =
            $this->cashTruth
                ->current();

        $assessment =
            new CashRiskAssessment(
                cashPosition: $truth->verifiedCash,

                outstandingInvoices: $truth->ledgerReceivables,

                knownLiabilities: $truth->knownLiabilities,

                unknownExposure: $truth->safeAvailableCash === null
                    ? $truth->knownNetPosition
                    : 0,

                confidence: $truth->cashConfidence,

                evidence: [
                    'verified_cash' => $truth->verifiedCash,

                    'ledger_receivables' => $truth->ledgerReceivables,

                    'known_liabilities' => $truth->knownLiabilities,

                    'safe_available_cash' => $truth->safeAvailableCash,

                    'cash_confidence' => $truth->cashConfidence,

                    'account_count' => $truth->accountCount,

                    'verified_account_count' => $truth->verifiedAccountCount,
                ]
            );

        $result =
            $this->cashManagement
                ->assess(
                    $assessment
                );

        if (
            $assessment->riskScore() === 0
            && $truth->cashConfidence === 100
        ) {
            return collect();
        }

        return collect([
            new ExecutiveReasoning(
                type: 'cash_management',

                clientId: null,

                client: null,

                title: 'Review cash risk exposure',

                description: $truth->cashConfidence < 100
                    ? 'Cash position cannot be fully verified and requires executive review.'
                    : 'Cash position requires executive review.',

                estimatedFinancialImpact: $truth->safeAvailableCash === null
    ? null
    : $assessment->unknownExposure,

                estimatedEffortMinutes: 30,

                confidence: $assessment->confidence,

                urgency: max(
                    50,
                    $assessment->riskScore()
                ),

                score: max(
                    50,
                    $assessment->riskScore()
                ),

                recommendedAction: $result['recommendations'][0]
                    ?? 'Review cash position',

                supportingEvidence: [
                    ...$assessment->evidence,

                    'risk_score' => $assessment->riskScore(),

                    'recommendations' => $result['recommendations'],
                ]
            ),
        ]);
    }
}
