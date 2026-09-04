<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGap;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionConstraint;
use App\Domains\Cfo\Decision\CfoDecisionContext;
use App\Domains\Cfo\Decision\CfoDecisionEvidence;
use App\Domains\Cfo\Decision\CfoDecisionPolicy;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class DiscretionarySpendDecisionPolicyTest extends TestCase
{
    public function test_policy_implements_authoritative_cfo_policy_contract(): void
    {
        $reflection =
            new ReflectionClass(
                DiscretionarySpendDecisionPolicy::class
            );

        $this->assertTrue(
            $reflection->implementsInterface(
                CfoDecisionPolicy::class
            )
        );
    }

    public function test_policy_supports_only_discretionary_spend_requests(): void
    {
        $policy =
            new DiscretionarySpendDecisionPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request(
                    amount: 5000
                )
            )
        );

        $this->assertFalse(
            $policy->supports(
                new CfoDecisionRequest(
                    key: 'hire_employee',

                    question: 'Can we hire another employee?',

                    parameters: [
                        'amount' => 5000,

                        'currency' => 'GBP',
                    ]
                )
            )
        );
    }

    public function test_policy_rejects_request_for_another_decision_type(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (
            new DiscretionarySpendDecisionPolicy
        )->decide(
            $this->context(
                safeAvailableCash: 20000,

                request: new CfoDecisionRequest(
                    key: 'hire_employee',

                    question: 'Can we hire another employee?',

                    parameters: [
                        'amount' => 5000,

                        'currency' => 'GBP',
                    ]
                )
            )
        );
    }

    public function test_policy_specific_parameters_fail_closed_instead_of_being_silently_ignored(): void
    {
        $invalid = [
            [],
            [
                'amount' => '5000',

                'currency' => 'GBP',
            ],
            [
                'amount' => 0,

                'currency' => 'GBP',
            ],
            [
                'amount' => -1,

                'currency' => 'GBP',
            ],
            [
                'amount' => 5000,

                'currency' => 'USD',
            ],
            [
                'amount' => 5000,

                'currency' => 'GBP',

                'recurring' => 'yes',
            ],
            [
                'amount' => 5000,

                'currency' => 'GBP',

                'future_magic' => true,
            ],
        ];

        foreach ($invalid as $parameters) {
            try {
                (
                    new DiscretionarySpendDecisionPolicy
                )->decide(
                    $this->context(
                        safeAvailableCash: 20000,

                        request: new CfoDecisionRequest(
                            key: DiscretionarySpendDecisionPolicy::KEY,

                            question: 'Can we make this spend?',

                            parameters: $parameters
                        )
                    )
                );

                $this->fail(
                    'Expected invalid discretionary-spend parameters to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_one_off_spend_within_safe_available_cash_is_recommended_as_supportable(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: 20000,

                    request: $this->request(
                        amount: 5000
                    )
                )
            );

        $this->assertSame(
            CfoDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            'The proposed one-off discretionary spend of £5,000.00 is financially supportable from established safe available cash.',
            $decision->recommendation
        );

        $this->assertSame(
            'Established safe available cash is £20,000.00. The proposed spend is £5,000.00 and would leave £15,000.00 of that established safe cash.',
            $decision->rationale
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );

        $supports =
            $decision->evidence
                ->where(
                    'position',
                    CfoDecisionEvidence::SUPPORTS
                );

        $this->assertCount(
            1,
            $supports
        );

        $this->assertSame(
            'business_state.financial.cash.safeAvailableCash',
            $supports->first()->source
        );
    }

    public function test_exact_safe_cash_boundary_is_supportable_without_inventing_a_reserve_threshold(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: 5000,

                    request: $this->request(
                        amount: 5000
                    )
                )
            );

        $this->assertSame(
            CfoDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringContainsString(
            'would leave £0.00',
            $decision->rationale
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );
    }

    public function test_spend_above_safe_cash_produces_recommended_do_not_spend_guidance(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: 20000,

                    request: $this->request(
                        amount: 25000
                    )
                )
            );

        /*
         * RECOMMENDED means the guidance is established.
         *
         * It does not mean that the recommended action is always "yes".
         */
        $this->assertSame(
            CfoDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            'Do not make the proposed one-off discretionary spend of £25,000.00 from current safe available cash.',
            $decision->recommendation
        );

        $this->assertSame(
            'The proposed spend exceeds established safe available cash of £20,000.00 by £5,000.00.',
            $decision->rationale
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );
    }

    public function test_negative_safe_cash_produces_established_do_not_spend_guidance(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: -2500,

                    request: $this->request(
                        amount: 1000
                    )
                )
            );

        $this->assertSame(
            CfoDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringStartsWith(
            'Do not make',
            $decision->recommendation
        );

        $this->assertStringContainsString(
            '£3,500.00',
            $decision->rationale
        );
    }

    public function test_unknown_safe_cash_defers_instead_of_treating_unknown_as_zero(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: null,

                    request: $this->request(
                        amount: 5000
                    )
                )
            );

        $this->assertSame(
            CfoDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (CfoDecisionConstraint $constraint): bool => $constraint->type
                            === CfoDecisionConstraint::BLOCKING
                        && $constraint->code
                            === 'safe_available_cash_unknown'
                )
        );

        $this->assertContains(
            'Complete current bank and liability evidence is not available, so safe available cash cannot be established.',
            $decision->missingTruth->all()
        );

        $this->assertFalse(
            $decision->evidence
                ->contains(
                    fn (CfoDecisionEvidence $evidence): bool => $evidence->position
                            === CfoDecisionEvidence::SUPPORTS
                )
        );
    }

    public function test_missing_expected_safe_cash_gap_still_fails_closed(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: null,

                    request: $this->request(
                        amount: 5000
                    ),

                    includeSafeCashGap: false
                )
            );

        $this->assertSame(
            CfoDecision::DEFERRED,
            $decision->status
        );

        $this->assertContains(
            'Safe available cash is not established from current business truth.',
            $decision->missingTruth->all()
        );
    }

    public function test_recurring_spend_defers_even_when_current_safe_cash_is_known(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: 50000,

                    request: $this->request(
                        amount: 5000,

                        recurring: true
                    )
                )
            );

        $this->assertSame(
            CfoDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (CfoDecisionConstraint $constraint): bool => $constraint->code
                            === 'forward_cash_truth_required'
                        && $constraint->type
                            === CfoDecisionConstraint::BLOCKING
                )
        );

        $this->assertContains(
            'Forward cash availability and committed obligations across the recurring decision period are not established by the current point-in-time Business State.',
            $decision->missingTruth->all()
        );

        $this->assertTrue(
            $decision->evidence
                ->contains(
                    fn (CfoDecisionEvidence $evidence): bool => $evidence->position
                            === CfoDecisionEvidence::CONTEXT
                        && str_contains(
                            $evidence->description,
                            'point-in-time position rather than a forward cash forecast'
                        )
                )
        );
    }

    public function test_recurring_spend_with_unknown_current_cash_preserves_both_blockers(): void
    {
        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: null,

                    request: $this->request(
                        amount: 5000,

                        recurring: true
                    )
                )
            );

        $codes =
            $decision->constraints
                ->map(
                    fn (CfoDecisionConstraint $constraint): string => $constraint->code
                )
                ->sort()
                ->values()
                ->all();

        $this->assertSame(
            [
                'forward_cash_truth_required',
                'safe_available_cash_unknown',
            ],
            $codes
        );

        $this->assertCount(
            2,
            $decision->missingTruth
        );
    }

    public function test_populated_safe_cash_without_authoritative_full_support_confidence_fails_closed(): void
    {
        foreach (
            [
                0,
                50,
                99,
            ] as $cashConfidence
        ) {
            $decision =
                (
                    new DiscretionarySpendDecisionPolicy
                )->decide(
                    $this->context(
                        safeAvailableCash: 20000,

                        request: $this->request(
                            amount: 5000
                        ),

                        cashConfidence: $cashConfidence
                    )
                );

            $this->assertSame(
                CfoDecision::DEFERRED,
                $decision->status
            );

            $this->assertNull(
                $decision->recommendation
            );

            $this->assertSame(
                0,
                $decision->confidence
            );

            $this->assertTrue(
                $decision->constraints
                    ->contains(
                        fn (CfoDecisionConstraint $constraint): bool => $constraint->code
                                === 'safe_cash_support_invalid'
                    )
            );

            $this->assertContains(
                'A safe available cash position satisfying the authoritative 100% cash-confidence contract is required.',
                $decision->missingTruth->all()
            );
        }
    }

    public function test_decision_preserves_request_identity_and_context_timestamp(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-04 17:00:00'
            );

        $request =
            $this->request(
                amount: 5000
            );

        $decision =
            (
                new DiscretionarySpendDecisionPolicy
            )->decide(
                $this->context(
                    safeAvailableCash: 20000,

                    request: $request,

                    asOf: $asOf
                )
            );

        $this->assertSame(
            $request->key,
            $decision->key
        );

        $this->assertSame(
            $request->question,
            $decision->question
        );

        $this->assertTrue(
            $decision->asOf->equalTo(
                $asOf
            )
        );
    }

    public function test_policy_has_no_priority_score_urgency_execution_persistence_or_llm_path(): void
    {
        $reflection =
            new ReflectionClass(
                DiscretionarySpendDecisionPolicy::class
            );

        foreach (
            [
                'priority',
                'score',
                'urgency',
                'execution',
                'executedAt',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    private function request(
        int|float $amount,
        bool $recurring = false
    ): CfoDecisionRequest {
        return new CfoDecisionRequest(
            key: DiscretionarySpendDecisionPolicy::KEY,

            question: 'Can the business safely make this discretionary spend?',

            parameters: [
                'amount' => $amount,

                'currency' => 'GBP',

                'recurring' => $recurring,
            ]
        );
    }

    private function context(
        ?float $safeAvailableCash,
        CfoDecisionRequest $request,
        int $cashConfidence = 100,
        bool $includeSafeCashGap = true,
        ?CarbonImmutable $asOf = null,
    ): CfoDecisionContext {
        $asOf ??=
            CarbonImmutable::parse(
                '2026-09-04 17:00:00'
            );

        $unknowns =
            collect();

        if (
            $safeAvailableCash === null
            && $includeSafeCashGap
        ) {
            $unknowns->push(
                new BusinessStateGap(
                    domain: 'financial',

                    type: 'safe_available_cash_unknown',

                    scope: 'business',

                    clientId: null,

                    client: null,

                    title: 'Safe available cash is unknown',

                    description: 'Complete current bank and liability evidence is not available, so safe available cash cannot be established.'
                )
            );
        }

        $cash =
            new CashTruth(
                accountCount: 1,

                verifiedAccountCount: $safeAvailableCash !== null
                        ? 1
                        : 0,

                freshAccountCount: $safeAvailableCash !== null
                        ? 1
                        : 0,

                staleAccountCount: 0,

                unverifiedAccountCount: $safeAvailableCash === null
                        ? 1
                        : 0,

                verifiedCash: $safeAvailableCash
                    ?? 0,

                reportedAccountingBalance: 0,

                reportedUnverifiedCardDebt: 0,

                creditCardDebt: 0,

                knownLiabilities: 0,

                knownNetPosition: $safeAvailableCash
                    ?? 0,

                safeAvailableCash: $safeAvailableCash,

                ledgerReceivables: 0,

                paymentsWaitingAllocation: 0,

                bankVerificationConfidence: $safeAvailableCash !== null
                        ? 100
                        : 0,

                bankFreshnessConfidence: $safeAvailableCash !== null
                        ? 100
                        : 0,

                liabilityConfidence: $safeAvailableCash !== null
                        ? 100
                        : 0,

                receivableConfidence: 100,

                cashConfidence: $cashConfidence,

                oldestBalanceAt: null,

                newestBalanceAt: null
            );

        $financial =
            new FinancialPosition(
                cash: $cash,

                receivables: new ReceivablesPosition(
                    ledgerOutstanding: 0,

                    paymentsWaitingAllocation: 0,

                    verifiedCollectible: null,

                    confidence: 100
                ),

                liabilities: new LiabilityPosition(
                    known: 0,

                    vat: 0,

                    paye: 0,

                    other: 0,

                    confidence: $safeAvailableCash !== null
                            ? 100
                            : 0,

                    coverageComplete: $safeAvailableCash !== null
                ),

                credit: new CreditTruth(
                    facilities: collect(),

                    facilityCount: 0,

                    verifiedFacilityCount: 0,

                    reportedExposure: 0,

                    verifiedExposure: 0,

                    reportedAvailableCredit: 0,

                    minimumPaymentsDue: 0,

                    confidence: 100
                ),

                confidence: $cashConfidence,

                asOf: $asOf
            );

        $state =
            new BusinessState(
                financial: $financial,

                revenue: new RevenueTruthSummary(
                    clientCount: 0,

                    grossInvoiced: 0,

                    paidAccordingToAccounting: 0,

                    outstanding: 0,

                    unrecoveredWorkValue: 0,

                    bankVerifiedPaymentValue: 0,

                    clientsWithOutstandingRevenue: 0,

                    clientsWithWeakPaymentEvidence: 0,

                    clientsWithoutWorkEvidence: 0,

                    averageCommercialConfidence: 0,

                    gaps: collect()
                ),

                clients: collect(),

                gaps: new BusinessStateGaps(
                    unknowns: $unknowns,

                    evidenceGaps: collect()
                ),

                asOf: $asOf
            );

        return new CfoDecisionContext(
            request: $request,

            state: $state,

            current: new BusinessStateBaseline(
                metrics: collect(),

                asOf: $asOf
            ),

            previous: null,

            changes: collect(),

            attention: collect(),

            explanations: collect()
        );
    }
}
