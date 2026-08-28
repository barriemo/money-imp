<?php

namespace App\Domains\BusinessBrain\FinancialPosition;

use App\Domains\BusinessBrain\CashTruth\CashTruthService;
use App\Domains\BusinessBrain\CreditTruth\CreditTruthService;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Carbon\CarbonImmutable;

class FinancialPositionService
{
    public function __construct(
        private CashTruthService $cashTruth,

        private CreditTruthService $creditTruth,

        private FinancialTruthService $financialTruth
    ) {}

    public function current(): FinancialPosition
    {
        $cash =
            $this->cashTruth
                ->current();

        $financial =
            $this->financialTruth
                ->build();

        $credit =
            $this->creditTruth
                ->current();

        $receivables =
            new ReceivablesPosition(
                ledgerOutstanding: (float) $financial[
                    'receivables'
                ]['ledger_outstanding'],

                paymentsWaitingAllocation: (float) $financial[
                    'receivables'
                ]['payments_waiting_allocation'],

                verifiedCollectible: $financial[
                    'receivables'
                ]['verified_collectible'] !== null
                        ? (float) $financial[
                            'receivables'
                        ]['verified_collectible']
                        : null,

                confidence: (int) $financial[
                    'confidence'
                ]['receivables']
            );

        $liabilityConfidence =
            (int) $financial[
                'confidence'
            ]['liabilities'];

        $liabilities =
            new LiabilityPosition(
                known: (float) $financial[
                    'liabilities'
                ]['total'],

                vat: (float) $financial[
                    'liabilities'
                ]['vat'],

                paye: (float) $financial[
                    'liabilities'
                ]['paye'],

                employerNic: (float) (
                    $financial['liabilities']['employer_nic']
                    ?? 0
                ),

                payroll: (float) (
                    $financial['liabilities']['payroll']
                    ?? 0
                ),

                other: (float) $financial[
                    'liabilities'
                ]['other'],

                confidence: $liabilityConfidence,

                /*
                 * Zero liabilities with zero confidence
                 * means "we do not know", not "nothing owed".
                 */
                coverageComplete: $liabilityConfidence === 100
            );

        return new FinancialPosition(
            cash: $cash,

            receivables: $receivables,

            liabilities: $liabilities,

            credit: $credit,

            confidence: min(
                $cash->cashConfidence,
                $receivables->confidence,
                $liabilities->confidence,
                $credit->confidence
            ),

            asOf: CarbonImmutable::now()
        );
    }
}
