<?php

namespace App\Domains\Cfo\Decision;

use App\Domains\BusinessBrain\BusinessState\BusinessStateGap;
use InvalidArgumentException;

class DiscretionarySpendDecisionPolicy implements CfoDecisionPolicy
{
    public const KEY =
        'discretionary_spend';

    private const ALLOWED_PARAMETERS = [
        'amount',
        'currency',
        'recurring',
    ];

    public function supports(
        CfoDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        CfoDecisionContext $context
    ): CfoDecision {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                'Discretionary spend policy cannot decide a different CFO decision request.'
            );
        }

        [
            $amount,
            $currency,
            $recurring,
        ] =
            $this->validatedParameters(
                $context->request
            );

        if ($recurring) {
            return $this->deferRecurringSpend(
                context: $context,

                amount: $amount,

                currency: $currency
            );
        }

        $cash =
            $context->state
                ->financial
                ->cash;

        if ($cash->safeAvailableCash === null) {
            return $this->deferUnknownSafeCash(
                context: $context,

                amount: $amount,

                currency: $currency
            );
        }

        return $this->decideOneOffSpend(
            context: $context,

            amount: $amount,

            currency: $currency,

            safeAvailableCash: $cash->safeAvailableCash,

            supportConfidence: $cash->cashConfidence
        );
    }

    /**
     * @return array{0: float, 1: string, 2: bool}
     */
    private function validatedParameters(
        CfoDecisionRequest $request
    ): array {
        $unknown =
            array_values(
                array_diff(
                    array_keys(
                        $request->parameters
                    ),
                    self::ALLOWED_PARAMETERS
                )
            );

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Discretionary spend request contains unsupported parameters: '
                .implode(
                    ', ',
                    $unknown
                )
                .'.'
            );
        }

        if (
            ! array_key_exists(
                'amount',
                $request->parameters
            )
        ) {
            throw new InvalidArgumentException(
                'Discretionary spend request requires an amount.'
            );
        }

        $amount =
            $request->parameters[
                'amount'
            ];

        if (
            ! is_int($amount)
            && ! is_float($amount)
        ) {
            throw new InvalidArgumentException(
                'Discretionary spend amount must be numeric.'
            );
        }

        $amount =
            (float) $amount;

        if (
            ! is_finite($amount)
            || $amount <= 0
        ) {
            throw new InvalidArgumentException(
                'Discretionary spend amount must be a positive finite value.'
            );
        }

        if (
            ! array_key_exists(
                'currency',
                $request->parameters
            )
        ) {
            throw new InvalidArgumentException(
                'Discretionary spend request requires a currency.'
            );
        }

        $currency =
            $request->parameters[
                'currency'
            ];

        if (
            ! is_string($currency)
            || $currency !== 'GBP'
        ) {
            throw new InvalidArgumentException(
                'Discretionary spend policy currently supports GBP only.'
            );
        }

        $recurring =
            $request->parameters[
                'recurring'
            ]
            ?? false;

        if (! is_bool($recurring)) {
            throw new InvalidArgumentException(
                'Discretionary spend recurring parameter must be boolean.'
            );
        }

        return [
            $amount,
            $currency,
            $recurring,
        ];
    }

    private function decideOneOffSpend(
        CfoDecisionContext $context,
        float $amount,
        string $currency,
        float $safeAvailableCash,
        int $supportConfidence,
    ): CfoDecision {
        if ($supportConfidence !== 100) {
            return $this->deferInconsistentSafeCash(
                context: $context,

                amount: $amount,

                currency: $currency
            );
        }

        $remaining =
            $safeAvailableCash
            - $amount;

        $support =
            new CfoDecisionEvidence(
                source: 'business_state.financial.cash.safeAvailableCash',

                description: sprintf(
                    'Safe available cash is established at %s.',
                    $this->money(
                        $safeAvailableCash,
                        $currency
                    )
                ),

                position: CfoDecisionEvidence::SUPPORTS,

                confidence: $supportConfidence,

                metadata: [
                    'safe_available_cash' => $safeAvailableCash,

                    'currency' => $currency,
                ]
            );

        $requestEvidence =
            $this->requestEvidence(
                amount: $amount,

                currency: $currency,

                recurring: false
            );

        if ($amount <= $safeAvailableCash) {
            return new CfoDecision(
                key: $context->request->key,

                question: $context->request->question,

                status: CfoDecision::RECOMMENDED,

                recommendation: sprintf(
                    'The proposed one-off discretionary spend of %s is financially supportable from established safe available cash.',
                    $this->money(
                        $amount,
                        $currency
                    )
                ),

                rationale: sprintf(
                    'Established safe available cash is %s. The proposed spend is %s and would leave %s of that established safe cash.',
                    $this->money(
                        $safeAvailableCash,
                        $currency
                    ),
                    $this->money(
                        $amount,
                        $currency
                    ),
                    $this->money(
                        $remaining,
                        $currency
                    )
                ),

                evidence: collect([
                    $requestEvidence,
                    $support,
                ]),

                constraints: collect(),

                confidence: $supportConfidence,

                missingTruth: collect(),

                asOf: $context->asOf()
            );
        }

        $shortfall =
            $amount
            - $safeAvailableCash;

        return new CfoDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CfoDecision::RECOMMENDED,

            recommendation: sprintf(
                'Do not make the proposed one-off discretionary spend of %s from current safe available cash.',
                $this->money(
                    $amount,
                    $currency
                )
            ),

            rationale: sprintf(
                'The proposed spend exceeds established safe available cash of %s by %s.',
                $this->money(
                    $safeAvailableCash,
                    $currency
                ),
                $this->money(
                    $shortfall,
                    $currency
                )
            ),

            evidence: collect([
                $requestEvidence,
                $support,
            ]),

            constraints: collect(),

            confidence: $supportConfidence,

            missingTruth: collect(),

            asOf: $context->asOf()
        );
    }

    private function deferUnknownSafeCash(
        CfoDecisionContext $context,
        float $amount,
        string $currency,
    ): CfoDecision {
        $gap =
            $this->safeCashUnknownGap(
                $context
            );

        $missingTruth =
            $gap?->description
            ?? 'Safe available cash is not established from current business truth.';

        $source =
            $gap !== null
                ? 'business_state.gap.'
                    .$gap->type
                : 'business_state.financial.cash.safeAvailableCash';

        return new CfoDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CfoDecision::DEFERRED,

            recommendation: null,

            rationale: 'A discretionary cash-spend recommendation requires established safe available cash.',

            evidence: collect([
                $this->requestEvidence(
                    amount: $amount,

                    currency: $currency,

                    recurring: false
                ),

                new CfoDecisionEvidence(
                    source: 'business_state.financial.cash.safeAvailableCash',

                    description: 'Safe available cash is currently unknown.',

                    position: CfoDecisionEvidence::CONTEXT,

                    confidence: 100,

                    metadata: [
                        'known' => false,
                    ]
                ),
            ]),

            constraints: collect([
                new CfoDecisionConstraint(
                    code: 'safe_available_cash_unknown',

                    description: 'Safe available cash must be established before this one-off discretionary spend can be recommended.',

                    type: CfoDecisionConstraint::BLOCKING,

                    source: $source,

                    confidence: 100
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                $missingTruth,
            ]),

            asOf: $context->asOf()
        );
    }

    private function deferRecurringSpend(
        CfoDecisionContext $context,
        float $amount,
        string $currency,
    ): CfoDecision {
        $cash =
            $context->state
                ->financial
                ->cash;

        $constraints =
            collect([
                new CfoDecisionConstraint(
                    code: 'forward_cash_truth_required',

                    description: 'A recurring discretionary commitment requires forward cash availability and obligation truth, not only the current point-in-time cash position.',

                    type: CfoDecisionConstraint::BLOCKING,

                    source: 'cfo_policy.discretionary_spend.recurring',

                    confidence: 100
                ),
            ]);

        $missingTruth =
            collect([
                'Forward cash availability and committed obligations across the recurring decision period are not established by the current point-in-time Business State.',
            ]);

        if ($cash->safeAvailableCash === null) {
            $gap =
                $this->safeCashUnknownGap(
                    $context
                );

            $constraints->push(
                new CfoDecisionConstraint(
                    code: 'safe_available_cash_unknown',

                    description: 'Current safe available cash is also not established.',

                    type: CfoDecisionConstraint::BLOCKING,

                    source: $gap !== null
                            ? 'business_state.gap.'
                                .$gap->type
                            : 'business_state.financial.cash.safeAvailableCash',

                    confidence: 100
                )
            );

            $missingTruth->push(
                $gap?->description
                ?? 'Safe available cash is not established from current business truth.'
            );
        }

        $evidence =
            collect([
                $this->requestEvidence(
                    amount: $amount,

                    currency: $currency,

                    recurring: true
                ),
            ]);

        if ($cash->safeAvailableCash !== null) {
            $evidence->push(
                new CfoDecisionEvidence(
                    source: 'business_state.financial.cash.safeAvailableCash',

                    description: sprintf(
                        'Current safe available cash is established at %s, but this is a point-in-time position rather than a forward cash forecast.',
                        $this->money(
                            $cash->safeAvailableCash,
                            $currency
                        )
                    ),

                    position: CfoDecisionEvidence::CONTEXT,

                    confidence: $cash->cashConfidence,

                    metadata: [
                        'safe_available_cash' => $cash->safeAvailableCash,

                        'currency' => $currency,
                    ]
                )
            );
        }

        return new CfoDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CfoDecision::DEFERRED,

            recommendation: null,

            rationale: 'Current safe available cash alone cannot establish whether a recurring discretionary commitment is supportable over time.',

            evidence: $evidence,

            constraints: $constraints->values(),

            confidence: 0,

            missingTruth: $missingTruth->values(),

            asOf: $context->asOf()
        );
    }

    private function deferInconsistentSafeCash(
        CfoDecisionContext $context,
        float $amount,
        string $currency,
    ): CfoDecision {
        return new CfoDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: CfoDecision::DEFERRED,

            recommendation: null,

            rationale: 'Safe available cash is populated but does not satisfy the authoritative 100% cash-confidence contract, so the policy fails closed.',

            evidence: collect([
                $this->requestEvidence(
                    amount: $amount,

                    currency: $currency,

                    recurring: false
                ),

                new CfoDecisionEvidence(
                    source: 'business_state.financial.cash.safeAvailableCash',

                    description: 'Safe available cash is present without the authoritative 100% cash-confidence contract.',

                    position: CfoDecisionEvidence::CONTEXT,

                    confidence: 100
                ),
            ]),

            constraints: collect([
                new CfoDecisionConstraint(
                    code: 'safe_cash_support_invalid',

                    description: 'Populated safe available cash requires cash confidence of exactly 100 before it can support CFO guidance.',

                    type: CfoDecisionConstraint::BLOCKING,

                    source: 'business_state.financial.cash.cashConfidence',

                    confidence: 100
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'A safe available cash position satisfying the authoritative 100% cash-confidence contract is required.',
            ]),

            asOf: $context->asOf()
        );
    }

    private function requestEvidence(
        float $amount,
        string $currency,
        bool $recurring,
    ): CfoDecisionEvidence {
        return new CfoDecisionEvidence(
            source: 'cfo_decision_request',

            description: sprintf(
                'The decision request specifies a %s discretionary spend of %s.',
                $recurring
                    ? 'recurring'
                    : 'one-off',
                $this->money(
                    $amount,
                    $currency
                )
            ),

            position: CfoDecisionEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'amount' => $amount,

                'currency' => $currency,

                'recurring' => $recurring,
            ]
        );
    }

    private function safeCashUnknownGap(
        CfoDecisionContext $context
    ): ?BusinessStateGap {
        $gap =
            $context->state
                ->gaps
                ->unknowns
                ->first(
                    fn (mixed $candidate): bool => $candidate instanceof BusinessStateGap
                        && $candidate->scope === 'business'
                        && $candidate->type === 'safe_available_cash_unknown'
                );

        return $gap instanceof BusinessStateGap
            ? $gap
            : null;
    }

    private function money(
        float $value,
        string $currency
    ): string {
        if ($currency !== 'GBP') {
            throw new InvalidArgumentException(
                'Discretionary spend money formatting supports GBP only.'
            );
        }

        return '£'
            .number_format(
                $value,
                2,
                '.',
                ','
            );
    }
}
