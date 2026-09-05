<?php

namespace App\Domains\Executive\Decision;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Delivery\Decision\DeliveryDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ManagementResponseReadinessPolicy
{
    public const KEY =
        'management_response_readiness';

    public function supports(
        ExecutiveDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        ExecutiveDecisionContext $context
    ): ExecutiveDecision {
        $specialists =
            $this->validatedSpecialists(
                $context
            );

        $deferred =
            $specialists
                ->filter(
                    fn (array $item): bool => $item['decision']->status === 'deferred'
                )
                ->values();

        if ($deferred->isNotEmpty()) {
            return $this->deferForSpecialistUncertainty(
                context: $context,

                specialists: $specialists,

                deferred: $deferred
            );
        }

        $conditional =
            $specialists
                ->filter(
                    fn (array $item): bool => $item['decision']->status === 'conditional'
                )
                ->values();

        if ($conditional->isNotEmpty()) {
            return $this->recommendConditionalManagementReview(
                context: $context,

                specialists: $specialists,

                conditional: $conditional
            );
        }

        return $this->recommendManagementReview(
            context: $context,

            specialists: $specialists
        );
    }

    /**
     * @return Collection<int, array{
     *     domain: string,
     *     decision: CfoDecision|CommercialDecision|DeliveryDecision
     * }>
     */
    private function validatedSpecialists(
        ExecutiveDecisionContext $context
    ): Collection {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Management response readiness policy does not support executive decision request %s.',
                    $context->request->key
                )
            );
        }

        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Management response readiness policy does not accept parameters.'
            );
        }

        $specialists =
            collect();

        if ($context->request->cfoRequest !== null) {
            if (! $context->cfoDecision instanceof CfoDecision) {
                throw new InvalidArgumentException(
                    'Management response readiness requires the explicitly requested CFO decision.'
                );
            }

            $specialists->push([
                'domain' => 'cfo',

                'decision' => $context->cfoDecision,
            ]);
        }

        if ($context->request->commercialRequest !== null) {
            if (! $context->commercialDecision instanceof CommercialDecision) {
                throw new InvalidArgumentException(
                    'Management response readiness requires the explicitly requested Commercial decision.'
                );
            }

            $specialists->push([
                'domain' => 'commercial',

                'decision' => $context->commercialDecision,
            ]);
        }

        if ($context->request->deliveryRequest !== null) {
            if (! $context->deliveryDecision instanceof DeliveryDecision) {
                throw new InvalidArgumentException(
                    'Management response readiness requires the explicitly requested Delivery decision.'
                );
            }

            $specialists->push([
                'domain' => 'delivery',

                'decision' => $context->deliveryDecision,
            ]);
        }

        if ($specialists->count() < 2) {
            throw new InvalidArgumentException(
                'Management response readiness requires at least two explicitly requested specialist decision domains.'
            );
        }

        return $specialists->values();
    }

    private function recommendManagementReview(
        ExecutiveDecisionContext $context,
        Collection $specialists,
    ): ExecutiveDecision {
        $evidence =
            $this->supportingEvidence(
                context: $context,

                specialists: $specialists
            );

        return new ExecutiveDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ExecutiveDecision::RECOMMENDED,

            recommendation: 'Proceed to human management review of this explicit cross-domain specialist decision set.',

            rationale: 'At least two explicitly requested specialist decisions are established and none is deferred or conditional. Executive recommends review of the decision set only; it does not merge, rank, reinterpret or execute the underlying specialist recommendations.',

            evidence: $evidence,

            constraints: collect(),

            confidence: $this->weakestSpecialistConfidence(
                $specialists
            ),

            missingTruth: collect(),

            asOf: $this->decisionAsOf(
                context: $context,

                specialists: $specialists
            )
        );
    }

    private function recommendConditionalManagementReview(
        ExecutiveDecisionContext $context,
        Collection $specialists,
        Collection $conditional,
    ): ExecutiveDecision {
        $constraints =
            $conditional
                ->map(
                    fn (array $item): ExecutiveDecisionConstraint => new ExecutiveDecisionConstraint(
                        code: $item['domain']
                            .'_decision_conditional',

                        description: sprintf(
                            'The explicitly requested %s specialist decision is conditional. Its specialist uncertainty must remain explicit during human management review.',
                            strtoupper(
                                $item['domain']
                            )
                        ),

                        type: ExecutiveDecisionConstraint::CONDITION,

                        source: $this->specialistSource(
                            domain: $item['domain'],

                            decision: $item['decision']
                        ),

                        confidence: 100,

                        metadata: [
                            'domain' => $item['domain'],

                            'decision_key' => $item['decision']->key,

                            'decision_status' => $item['decision']->status,

                            'decision_confidence' => $item['decision']->confidence,

                            'specialist_as_of' => $item['decision']
                                ->asOf
                                ->toIso8601String(),

                            'specialist_constraint_count' => $item['decision']
                                ->constraints
                                ->count(),

                            'specialist_missing_truth_count' => $item['decision']
                                ->missingTruth
                                ->count(),
                        ]
                    )
                )
                ->values();

        $missingTruth =
            $this->specialistMissingTruth(
                $conditional
            );

        return new ExecutiveDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ExecutiveDecision::CONDITIONAL,

            recommendation: 'Proceed to human management review of this explicit cross-domain specialist decision set subject to the specialist conditions already recorded.',

            rationale: 'No explicitly requested specialist decision is deferred, but at least one specialist decision is conditional. Executive preserves those conditions rather than converting the set into an unconditional recommendation.',

            evidence: $this->supportingEvidence(
                context: $context,

                specialists: $specialists
            ),

            constraints: $constraints,

            confidence: $this->weakestSpecialistConfidence(
                $specialists
            ),

            missingTruth: $missingTruth,

            asOf: $this->decisionAsOf(
                context: $context,

                specialists: $specialists
            )
        );
    }

    private function deferForSpecialistUncertainty(
        ExecutiveDecisionContext $context,
        Collection $specialists,
        Collection $deferred,
    ): ExecutiveDecision {
        $constraints =
            $deferred
                ->map(
                    fn (array $item): ExecutiveDecisionConstraint => new ExecutiveDecisionConstraint(
                        code: $item['domain']
                            .'_decision_deferred',

                        description: sprintf(
                            'The explicitly requested %s specialist decision is deferred and cannot support a cross-domain Executive recommendation yet.',
                            strtoupper(
                                $item['domain']
                            )
                        ),

                        type: ExecutiveDecisionConstraint::BLOCKING,

                        source: $this->specialistSource(
                            domain: $item['domain'],

                            decision: $item['decision']
                        ),

                        confidence: 100,

                        metadata: [
                            'domain' => $item['domain'],

                            'decision_key' => $item['decision']->key,

                            'decision_status' => $item['decision']->status,

                            'decision_confidence' => $item['decision']->confidence,

                            'specialist_as_of' => $item['decision']
                                ->asOf
                                ->toIso8601String(),

                            'specialist_constraint_count' => $item['decision']
                                ->constraints
                                ->count(),

                            'specialist_missing_truth_count' => $item['decision']
                                ->missingTruth
                                ->count(),
                        ]
                    )
                )
                ->values();

        return new ExecutiveDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ExecutiveDecision::DEFERRED,

            recommendation: null,

            rationale: 'At least one explicitly requested specialist decision is deferred. Executive cannot establish a cross-domain management response while part of the requested specialist decision set remains unresolved.',

            evidence: $this->contextEvidence(
                context: $context,

                specialists: $specialists
            ),

            constraints: $constraints,

            confidence: 0,

            missingTruth: $this->specialistMissingTruth(
                $deferred
            ),

            asOf: $this->decisionAsOf(
                context: $context,

                specialists: $specialists
            )
        );
    }

    private function supportingEvidence(
        ExecutiveDecisionContext $context,
        Collection $specialists,
    ): Collection {
        $evidence =
            $specialists
                ->map(
                    fn (array $item): ExecutiveDecisionEvidence => $this->specialistEvidence(
                        domain: $item['domain'],

                        decision: $item['decision'],

                        position: ExecutiveDecisionEvidence::SUPPORTS,

                        confidence: $item['decision']->confidence
                    )
                )
                ->values();

        $evidence->push(
            $this->businessBrainContextEvidence(
                $context
            )
        );

        return $evidence;
    }

    private function contextEvidence(
        ExecutiveDecisionContext $context,
        Collection $specialists,
    ): Collection {
        $evidence =
            $specialists
                ->map(
                    fn (array $item): ExecutiveDecisionEvidence => $this->specialistEvidence(
                        domain: $item['domain'],

                        decision: $item['decision'],

                        position: ExecutiveDecisionEvidence::CONTEXT,

                        confidence: 100
                    )
                )
                ->values();

        $evidence->push(
            $this->businessBrainContextEvidence(
                $context
            )
        );

        return $evidence;
    }

    private function specialistEvidence(
        string $domain,
        CfoDecision|CommercialDecision|DeliveryDecision $decision,
        string $position,
        int $confidence,
    ): ExecutiveDecisionEvidence {
        return new ExecutiveDecisionEvidence(
            source: $this->specialistSource(
                domain: $domain,

                decision: $decision
            ),

            description: sprintf(
                'The explicitly requested %s specialist decision %s is %s with recommendation confidence %d%%.',
                strtoupper(
                    $domain
                ),
                $decision->key,
                $decision->status,
                $decision->confidence
            ),

            position: $position,

            confidence: $confidence,

            metadata: [
                'domain' => $domain,

                'decision_key' => $decision->key,

                'decision_status' => $decision->status,

                'decision_confidence' => $decision->confidence,

                'specialist_as_of' => $decision
                    ->asOf
                    ->toIso8601String(),
            ]
        );
    }

    private function businessBrainContextEvidence(
        ExecutiveDecisionContext $context
    ): ExecutiveDecisionEvidence {
        return new ExecutiveDecisionEvidence(
            source: 'business_brain.executive_context',

            description: 'Executive context contains one coherent Business Brain state observation with its derived temporal change, attention and explanation context.',

            position: ExecutiveDecisionEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'business_state_as_of' => $context
                    ->asOf()
                    ->toIso8601String(),

                'has_previous_baseline' => $context
                    ->hasComparisonBaseline(),

                'change_count' => $context
                    ->changes
                    ->count(),

                'attention_count' => $context
                    ->attention
                    ->count(),

                'explanation_count' => $context
                    ->explanations
                    ->count(),
            ]
        );
    }

    private function specialistMissingTruth(
        Collection $specialists
    ): Collection {
        return $specialists
            ->flatMap(
                function (array $item): Collection {
                    $missing =
                        $item['decision']
                            ->missingTruth
                            ->values();

                    if ($missing->isEmpty()) {
                        return collect([
                            sprintf(
                                '%s specialist decision remains unresolved under its own evidence boundary.',
                                strtoupper(
                                    $item['domain']
                                )
                            ),
                        ]);
                    }

                    return $missing
                        ->map(
                            fn (string $truth): string => sprintf(
                                '%s specialist: %s',
                                strtoupper(
                                    $item['domain']
                                ),
                                $truth
                            )
                        )
                        ->values();
                }
            )
            ->values();
    }

    private function weakestSpecialistConfidence(
        Collection $specialists
    ): int {
        return (int) $specialists
            ->min(
                fn (array $item): int => $item['decision']->confidence
            );
    }

    private function specialistSource(
        string $domain,
        CfoDecision|CommercialDecision|DeliveryDecision $decision,
    ): string {
        return $domain
            .'_decision.'
            .$decision->key;
    }

    private function decisionAsOf(
        ExecutiveDecisionContext $context,
        Collection $specialists,
    ): CarbonImmutable {
        $asOf =
            $context->asOf();

        foreach ($specialists as $item) {
            if (
                $item['decision']
                    ->asOf
                    ->greaterThan(
                        $asOf
                    )
            ) {
                $asOf =
                    $item['decision']
                        ->asOf;
            }
        }

        return $asOf;
    }
}
