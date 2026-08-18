<?php

namespace App\Domains\BusinessBrain\Reasoning\Engines;

use App\Domains\BusinessBrain\Actions\ExecutiveActionFingerprint;
use App\Domains\BusinessBrain\Decisions\BusinessDecision;
use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Domains\BusinessBrain\Learning\LearningScoreModifier;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use App\Domains\BusinessBrain\Reasoning\Scoring\ExecutiveReasoningScoreCalculator;
use Illuminate\Support\Collection;

class OpportunityEngine
{
    public function __construct(
        private BusinessDecisionService $decisions,

        private ExecutiveReasoningScoreCalculator $scores,

        private LearningScoreModifier $learning,

        private CashManagementReasoningEngine $cash,

        private ReceivableRecoveryReasoningEngine $receivables
    ) {}

    public function current(): Collection
    {
        $business =
            $this->decisions
                ->today()
                ->reject(
                    fn (BusinessDecision $decision) => $decision->type === 'collections'
                )
                ->map(
                    fn (BusinessDecision $decision) => $this->fromDecision(
                        $decision
                    )
                );

        $cash =
            $this->cash
                ->current();

        $receivables =
            $this->receivables
                ->current();

        return $business
            ->merge(
                $cash
            )
            ->merge(
                $receivables
            )
            ->unique(
                fn (ExecutiveReasoning $reasoning) => app(
                    ExecutiveActionFingerprint::class
                )->make(
                    $reasoning
                )
            )
            ->sortByDesc(
                'score'
            )
            ->values();
    }

    private function fromDecision(
        BusinessDecision $decision
    ): ExecutiveReasoning {
        $effort =
            $this->estimatedEffort(
                $decision
            );

        $baseScore =
            $this->scores
                ->calculate(
                    financialImpact: $decision->value,

                    urgency: $decision->priority,

                    confidence: $decision->confidence,

                    effortMinutes: $effort
                );

        $learningModifier =
            $this->learning
                ->forType(
                    $this->reasoningType(
                        $decision
                    )
                );

        $finalScore =
            max(
                0,
                min(
                    100,
                    $baseScore + $learningModifier
                )
            );

        return new ExecutiveReasoning(
            type: $this->reasoningType(
                $decision
            ),

            clientId: $decision->clientId,

            client: $decision->client,

            title: $this->title(
                $decision
            ),

            description: $decision->reason,

            estimatedFinancialImpact: $decision->value,

            estimatedEffortMinutes: $effort,

            confidence: $decision->confidence,

            urgency: $decision->priority,

            score: $finalScore,

            recommendedAction: $decision->action,

            supportingEvidence: [
                'decision_type' => $decision->type,

                'decision_priority' => $decision->priority,

                'decision_confidence' => $decision->confidence,

                'estimated_effort_source' => 'decision_type_default',

                'base_score' => $baseScore,

                'learning_modifier' => $learningModifier,

                'final_score' => $finalScore,
            ]
        );
    }

    private function reasoningType(
        BusinessDecision $decision
    ): string {
        return match ($decision->type) {
            'collections',
            'invoice_delivery' => 'financial_opportunity',

            'billing_dormancy' => 'commercial_opportunity',

            'payment_evidence',
            'bank_matching' => 'financial_control',

            'delivery_evidence' => 'delivery_control',

            'charlie_follow_up' => 'operational_opportunity',

            default => 'executive_action',
        };
    }

    private function title(
        BusinessDecision $decision
    ): string {
        return match ($decision->type) {
            'collections' => 'Recover overdue revenue',

            'invoice_delivery' => 'Convert delivered work into revenue',

            'billing_dormancy' => 'Review dormant commercial relationship',

            'payment_evidence' => 'Strengthen payment evidence',

            'bank_matching' => 'Resolve unmatched banking evidence',

            'delivery_evidence' => 'Complete delivery evidence',

            'charlie_follow_up' => 'Resolve high-priority operational findings',

            default => 'Executive action required',
        };
    }

    private function estimatedEffort(
        BusinessDecision $decision
    ): ?int {
        return match ($decision->type) {
            'collections' => 10,

            'invoice_delivery' => 20,

            'payment_evidence' => 30,

            'bank_matching' => 30,

            'delivery_evidence' => 30,

            'charlie_follow_up' => 30,

            'billing_dormancy' => 45,

            default => null,
        };
    }
}
