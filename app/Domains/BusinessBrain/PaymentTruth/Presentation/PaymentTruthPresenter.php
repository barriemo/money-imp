<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Presentation;

use App\Domains\BusinessBrain\PaymentTruth\Position\PaymentTruthPosition;

class PaymentTruthPresenter
{
    public function present(
        PaymentTruthPosition $position
    ): array {
        return [
            'headline' => 'Customer Payment Truth',

            'received' => $this->money(
                $position->canonicalReceived
            ),

            'allocated' => $this->money(
                $position->allocatedReceived
            ),

            'suggested' => $this->money(
                $position->suggestedReceived
            ),

            'unmatched' => $this->money(
                $position->unmatchedReceived
            ),

            'payment_count' => $position->paymentCount,

            'allocated_payment_count' => $position
                ->allocatedPaymentCount,

            'suggested_payment_count' => $position
                ->suggestedPaymentCount,

            'unmatched_payment_count' => $position
                ->unmatchedPaymentCount,

            'duplicate_evidence' => [
                'groups' => $position->duplicateEvidenceGroups,

                'value' => $this->money(
                    $position->duplicateEvidenceValue
                ),
            ],

            'confidence' => $position->confidence.'%',
        ];
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            $value,
            2
        );
    }
}
