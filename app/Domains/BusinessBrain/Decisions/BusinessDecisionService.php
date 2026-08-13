<?php

namespace App\Domains\BusinessBrain\Decisions;

use App\Domains\BusinessBrain\Interrogation\Attention\ClientAttentionPosition;
use App\Domains\BusinessBrain\Interrogation\Attention\ClientAttentionService;
use App\Domains\BusinessBrain\RevenueTruth\CommercialGap;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummaryService;
use Illuminate\Support\Collection;

class BusinessDecisionService
{
    public function __construct(
        private ClientAttentionService $attention,

        private RevenueTruthSummaryService $revenueTruth
    ) {}

    public function today(): Collection
    {
        $attentionDecisions =
            $this->attention
                ->ranked()
                ->flatMap(
                    fn (ClientAttentionPosition $position) => $this->decisionsFor(
                        $position
                    )
                );

        $commercialDecisions =
            $this->revenueTruth
                ->current()
                ->gaps
                ->map(
                    fn (CommercialGap $gap) => $this->decisionForCommercialGap(
                        $gap
                    )
                )
                ->filter();

        return $attentionDecisions
            ->merge(
                $commercialDecisions
            )
            ->unique(
                fn (BusinessDecision $decision) => $decision->clientId
                    .':'
                    .$decision->type
            )
            ->sortByDesc(
                'priority'
            )
            ->values();
    }

    private function decisionForCommercialGap(
        CommercialGap $gap
    ): ?BusinessDecision {
        return match ($gap->type) {
            'outstanding_revenue' => new BusinessDecision(
                type: 'collections',

                clientId: $gap->clientId,

                client: $gap->client,

                action: 'Review and chase overdue balance.',

                reason: $gap->description,

                priority: $gap->priority,

                value: $gap->value,

                confidence: $gap->confidence
            ),

            'unbilled_work' => new BusinessDecision(
                type: 'invoice_delivery',

                clientId: $gap->clientId,

                client: $gap->client,

                action: 'Review completed commercial work and create the required invoice.',

                reason: $gap->description,

                priority: $gap->priority,

                value: $gap->value,

                confidence: $gap->confidence
            ),

            'weak_payment_evidence' => new BusinessDecision(
                type: 'payment_evidence',

                clientId: $gap->clientId,

                client: $gap->client,

                action: 'Reconcile accounting payment claims against bank evidence.',

                reason: $gap->description,

                priority: $gap->priority,

                value: null,

                confidence: $gap->confidence
            ),

            'missing_work_evidence' => new BusinessDecision(
                type: 'delivery_evidence',

                clientId: $gap->clientId,

                client: $gap->client,

                action: 'Capture or review delivery evidence for this client.',

                reason: $gap->description,

                priority: $gap->priority,

                value: null,

                confidence: $gap->confidence
            ),

            default => null,
        };
    }

    private function decisionsFor(
        ClientAttentionPosition $position
    ): Collection {
        $decisions =
            collect();

        if ($position->overdue > 0) {
            $decisions->push(
                new BusinessDecision(
                    type: 'collections',

                    clientId: $position->clientId,

                    client: $position->client,

                    action: 'Review and chase overdue balance.',

                    reason: sprintf(
                        '£%s is overdue.',
                        number_format(
                            $position->overdue,
                            2
                        )
                    ),

                    priority: min(
                        100,
                        75 + (int) min(
                            25,
                            $position->overdue / 500
                        )
                    ),

                    value: $position->overdue,

                    confidence: 100
                )
            );
        }

        if ($position->billingDormant) {
            $decisions->push(
                new BusinessDecision(
                    type: 'billing_dormancy',

                    clientId: $position->clientId,

                    client: $position->client,

                    action: 'Review whether this client should be billed or commercially re-engaged.',

                    reason: sprintf(
                        'No invoice has been raised for %d days.',
                        $position->daysSinceLastInvoice
                    ),

                    priority: min(
                        78,
                        45 + (int) min(
                            33,
                            ($position->daysSinceLastInvoice ?? 0) / 15
                        )
                    ),

                    value: null,

                    confidence: 90
                )
            );
        }

        if ($position->highPriorityFindings > 0) {
            $decisions->push(
                new BusinessDecision(
                    type: 'charlie_follow_up',

                    clientId: $position->clientId,

                    client: $position->client,

                    action: 'Review current high-priority Charlie findings.',

                    reason: sprintf(
                        '%d high-priority finding%s remain open.',
                        $position->highPriorityFindings,
                        $position->highPriorityFindings === 1
                            ? ''
                            : 's'
                    ),

                    priority: min(
                        85,
                        55 + ($position->highPriorityFindings * 5)
                    ),

                    value: null,

                    confidence: 85
                )
            );
        }

        if ($position->unmatchedTransactions > 0) {
            $decisions->push(
                new BusinessDecision(
                    type: 'bank_matching',

                    clientId: $position->clientId,

                    client: $position->client,

                    action: 'Review unmatched bank transactions.',

                    reason: sprintf(
                        '%d unmatched bank transaction%s remain.',
                        $position->unmatchedTransactions,
                        $position->unmatchedTransactions === 1
                            ? ''
                            : 's'
                    ),

                    priority: min(
                        80,
                        45 + ($position->unmatchedTransactions * 3)
                    ),

                    value: null,

                    confidence: 95
                )
            );
        }

        return $decisions;
    }
}
