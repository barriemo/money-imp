<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGap;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use InvalidArgumentException;

class BusinessStateExplanationEvidenceService
{
    public function forChange(
        BusinessStateChange $change,
        BusinessState $state
    ): BusinessStateExplanationEvidenceSet {
        /*
         * Explanation evidence must describe the exact current state
         * represented by the change.
         *
         * Evidence from a later or earlier BusinessState must not be
         * silently attached to a different temporal observation.
         */
        if (
            ! $state->asOf->equalTo(
                $change->currentAsOf
            )
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence state must match the change current timestamp.'
            );
        }

        $evidence =
            collect([
                $this->changeContext(
                    $change
                ),
            ]);

        $interpretation =
            null;

        $impact =
            $this->impact(
                $change
            );

        /*
         * Current truth gaps may provide context about why a value is
         * presently unknowable.
         *
         * They become SUPPORTS only where the authoritative gap contract
         * itself names the missing truth dependency.
         */
        if (
            $change->kind
            === BusinessStateChange::BECAME_UNKNOWN
        ) {
            [
                $gap,
                $position,
                $candidate,
            ] =
                $this->truthLossEvidence(
                    $change,
                    $state
                );

            if ($gap !== null) {
                $evidence->push(
                    new BusinessStateExplanationEvidence(
                        source: 'business_state.gap.'
                            .$gap->type,

                        description: $gap->description,

                        position: $position,

                        confidence: 100,

                        metadata: [
                            'domain' => $gap->domain,

                            'type' => $gap->type,

                            'scope' => $gap->scope,

                            'client_id' => $gap->clientId,

                            'client' => $gap->client,
                        ]
                    )
                );
            }

            $interpretation =
                $candidate;
        }

        return new BusinessStateExplanationEvidenceSet(
            observation: $change,

            evidence: $evidence->values(),

            interpretation: $interpretation,

            impact: $impact
        );
    }

    private function changeContext(
        BusinessStateChange $change
    ): BusinessStateExplanationEvidence {
        return new BusinessStateExplanationEvidence(
            source: $change->current->source,

            description: $this->changeDescription(
                $change
            ),

            position: BusinessStateExplanationEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'metric_key' => $change->current->key(),

                'metric' => $change->current->metric,

                'kind' => $change->kind,

                'previous_known' => $change->previous->known,

                'previous_value' => $change->previous->value,

                'current_known' => $change->current->known,

                'current_value' => $change->current->value,

                'previous_as_of' => $change->previousAsOf
                    ->toIso8601String(),

                'current_as_of' => $change->currentAsOf
                    ->toIso8601String(),
            ]
        );
    }

    /**
     * @return array{
     *     0: ?BusinessStateGap,
     *     1: string,
     *     2: ?string
     * }
     */
    private function truthLossEvidence(
        BusinessStateChange $change,
        BusinessState $state
    ): array {
        return match (
            $change->current->metric
        ) {
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH => $this->supportedTruthLoss(
                state: $state,

                gapType: 'safe_available_cash_unknown',

                interpretation: 'Safe available cash became unknown because complete current bank and liability evidence is not available.'
            ),

            BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE => $this->supportedTruthLoss(
                state: $state,

                gapType: 'liability_coverage_incomplete',

                interpretation: 'Total liability exposure became unknown because liability coverage is incomplete.'
            ),

            /*
             * The verified-collectible gap proves only that the value
             * cannot currently be established. It does not identify which
             * evidence change caused the loss, so it remains context.
             */
            BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES => $this->contextualTruthLoss(
                state: $state,

                gapType: 'verified_collectible_unknown'
            ),

            default => [
                null,
                BusinessStateExplanationEvidence::CONTEXT,
                null,
            ],
        };
    }

    /**
     * @return array{
     *     0: ?BusinessStateGap,
     *     1: string,
     *     2: ?string
     * }
     */
    private function supportedTruthLoss(
        BusinessState $state,
        string $gapType,
        string $interpretation,
    ): array {
        $gap =
            $this->businessUnknownGap(
                state: $state,

                type: $gapType
            );

        if ($gap === null) {
            return [
                null,
                BusinessStateExplanationEvidence::CONTEXT,
                null,
            ];
        }

        return [
            $gap,
            BusinessStateExplanationEvidence::SUPPORTS,
            $interpretation,
        ];
    }

    /**
     * @return array{
     *     0: ?BusinessStateGap,
     *     1: string,
     *     2: ?string
     * }
     */
    private function contextualTruthLoss(
        BusinessState $state,
        string $gapType,
    ): array {
        return [
            $this->businessUnknownGap(
                state: $state,

                type: $gapType
            ),
            BusinessStateExplanationEvidence::CONTEXT,
            null,
        ];
    }

    private function businessUnknownGap(
        BusinessState $state,
        string $type
    ): ?BusinessStateGap {
        $gap =
            $state->gaps
                ->unknowns
                ->first(
                    fn (mixed $candidate): bool => $candidate instanceof BusinessStateGap
                        && $candidate->scope === 'business'
                        && $candidate->type === $type
                );

        return $gap instanceof BusinessStateGap
            ? $gap
            : null;
    }

    private function changeDescription(
        BusinessStateChange $change
    ): string {
        $metric =
            $change->current->metric;

        return match ($change->kind) {
            BusinessStateChange::BECAME_KNOWN => sprintf(
                'Business-state metric %s became known at %s; it was previously unknown.',
                $metric,
                $this->value(
                    $change->current->value
                )
            ),

            BusinessStateChange::BECAME_UNKNOWN => sprintf(
                'Business-state metric %s became unknown; its previous established value was %s.',
                $metric,
                $this->value(
                    $change->previous->value
                )
            ),

            BusinessStateChange::INCREASED => sprintf(
                'Business-state metric %s increased from %s to %s.',
                $metric,
                $this->value(
                    $change->previous->value
                ),
                $this->value(
                    $change->current->value
                )
            ),

            BusinessStateChange::DECREASED => sprintf(
                'Business-state metric %s decreased from %s to %s.',
                $metric,
                $this->value(
                    $change->previous->value
                ),
                $this->value(
                    $change->current->value
                )
            ),

            default => throw new InvalidArgumentException(
                'Unsupported business-state change kind.'
            ),
        };
    }

    private function impact(
        BusinessStateChange $change
    ): string {
        if (
            $change->kind
            === BusinessStateChange::BECAME_UNKNOWN
        ) {
            return match (
                $change->current->metric
            ) {
                BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH => 'Money Imp can no longer safely state available cash.',

                BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES => 'Money Imp can no longer establish verified collectible receivables.',

                BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE => 'Known liability exposure remains available, but total liability exposure cannot be established.',

                default => sprintf(
                    'The current business state can no longer establish %s.',
                    $this->label(
                        $change->current->metric
                    )
                ),
            };
        }

        return match ($change->kind) {
            BusinessStateChange::BECAME_KNOWN => sprintf(
                'The current business state can now establish %s.',
                $this->label(
                    $change->current->metric
                )
            ),

            BusinessStateChange::INCREASED => sprintf(
                'Recorded %s is higher than in the captured baseline.',
                $this->label(
                    $change->current->metric
                )
            ),

            BusinessStateChange::DECREASED => sprintf(
                'Recorded %s is lower than in the captured baseline.',
                $this->label(
                    $change->current->metric
                )
            ),

            default => throw new InvalidArgumentException(
                'Unsupported business-state change kind.'
            ),
        };
    }

    private function label(
        string $metric
    ): string {
        return str_replace(
            '_',
            ' ',
            $metric
        );
    }

    private function value(
        int|float|null $value
    ): string {
        if ($value === null) {
            return 'unknown';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(
            rtrim(
                number_format(
                    $value,
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
