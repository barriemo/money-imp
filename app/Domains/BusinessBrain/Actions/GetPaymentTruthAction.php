<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Domains\BusinessBrain\Insights\BusinessInsight;
use App\Domains\BusinessBrain\Insights\BusinessInsightBuilder;
use App\Domains\BusinessBrain\PaymentTruth\Position\PaymentTruthPositionService;

class GetPaymentTruthAction
{
    public function __construct(
        private PaymentTruthPositionService $position,

        private BusinessInsightBuilder $insights
    ) {}

    public function execute(): BusinessInsight
    {
        $position =
            $this->position
                ->current();

        $needsAttention =
            $position->suggestedReceived > 0
            || $position->unmatchedReceived > 0;

        $builder =
            $this->insights
                ->headline(
                    'Customer Payment Truth'
                )
                ->status(
                    $needsAttention
                        ? 'needs_attention'
                        : 'healthy'
                )
                ->summary(
                    $this->summary(
                        $position->canonicalReceived,
                        $position->allocatedReceived,
                        $position->suggestedReceived,
                        $position->unmatchedReceived
                    )
                )
                ->metric(
                    'received',
                    $this->money(
                        $position->canonicalReceived
                    )
                )
                ->metric(
                    'confirmed',
                    $this->money(
                        $position->allocatedReceived
                    )
                )
                ->metric(
                    'suggested',
                    $this->money(
                        $position->suggestedReceived
                    )
                )
                ->metric(
                    'unmatched',
                    $this->money(
                        $position->unmatchedReceived
                    )
                )
                ->metric(
                    'payment_count',
                    $position->paymentCount
                )
                ->metric(
                    'confirmed_payment_count',
                    $position->allocatedPaymentCount
                )
                ->metric(
                    'suggested_payment_count',
                    $position->suggestedPaymentCount
                )
                ->metric(
                    'unmatched_payment_count',
                    $position->unmatchedPaymentCount
                )
                ->metric(
                    'duplicate_evidence_groups',
                    $position->duplicateEvidenceGroups
                )
                ->metric(
                    'duplicate_evidence_value',
                    $this->money(
                        $position->duplicateEvidenceValue
                    )
                )
                ->confidence(
                    $position->confidence
                );

        if ($position->suggestedReceived > 0) {
            $builder
                ->risk(
                    'Suggested invoice matches remain unconfirmed.'
                )
                ->action(
                    'Review suggested customer payment allocations.'
                );
        }

        if ($position->unmatchedReceived > 0) {
            $builder
                ->risk(
                    'Customer receipts exist with no invoice allocation evidence.'
                )
                ->action(
                    'Investigate unmatched customer receipts.'
                );
        }

        return $builder->build();
    }

    private function summary(
        float $received,
        float $confirmed,
        float $suggested,
        float $unmatched
    ): string {
        return sprintf(
            'Money Imp has identified %s of canonical customer receipts: %s confirmed against invoices, %s with suggested invoice matches, and %s currently unmatched.',
            $this->money($received),
            $this->money($confirmed),
            $this->money($suggested),
            $this->money($unmatched)
        );
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
