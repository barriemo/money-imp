<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

final class StatutorySettlementEvidenceService implements StatutorySettlementEvidenceProvider
{
    public function __construct(

        private readonly StatutoryPaymentEvidenceService $payments,

    ) {}

    public function assess(): StatutorySettlementEvidence
    {

        $categories = [];

        foreach ($this->payments->current() as $payment) {

            if ($payment->taxType === null) {

                continue;

            }

            $categories[$payment->taxType] ??= [

                'payments_observed' => true,

                'amount' => 0,

                'transactions' => 0,

            ];

            $categories[$payment->taxType]['amount'] += $payment->amount;

            $categories[$payment->taxType]['transactions']++;

        }

        $total = array_sum(

            array_column($categories, 'amount')

        );

        return new StatutorySettlementEvidence(

            $categories,

            $total,

            count($categories) > 0

        );

    }
}
