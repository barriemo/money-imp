<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchResult;
use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentDecisionConstraint;
use App\Domains\Payment\Decision\PaymentDecisionContext;
use App\Domains\Payment\Decision\PaymentDecisionEvidence;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentEvidenceConclusionReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_payment_evidence_conclusion_request(): void
    {
        $policy =
            new PaymentEvidenceConclusionReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request()
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'some-other-payment-question'
                )
            )
        );
    }

    public function test_unsupported_request_fails_explicitly(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment evidence conclusion readiness policy does not support decision request some-other-payment-question.'
        );

        $this->policy()
            ->decide(
                $this->context(
                    state: 'supported_payment_candidate_found',

                    request: $this->request(
                        key: 'some-other-payment-question'
                    )
                )
            );
    }

    public function test_v1_policy_rejects_parameters(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment evidence conclusion readiness policy does not accept parameters.'
        );

        $this->policy()
            ->decide(
                $this->context(
                    state: 'supported_payment_candidate_found',

                    request: $this->request(
                        parameters: [
                            'mode' => 'allocate',
                        ]
                    )
                )
            );
    }

    public function test_supported_payment_candidate_is_recommended_as_bounded_human_evidence_conclusion(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'supported_payment_candidate_found',
                        supportedCandidates: [
                            [
                                'transaction_id' => 'transaction-1',
                            ],
                        ]
                    )
                );

        $this->assertSame(
            PaymentDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringContainsString(
            'supports at least one payment candidate',
            $decision->recommendation
        );

        $this->assertStringContainsString(
            'does not by itself prove that payment occurred',
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

        $this->assertSame(
            PaymentDecisionEvidence::SUPPORTS,
            $decision
                ->evidence
                ->first()
                ->position
        );
    }

    public function test_no_supported_candidate_is_a_recommended_bounded_negative_conclusion_not_non_payment_truth(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'no_supported_payment_candidate_found'
                    )
                );

        $this->assertSame(
            PaymentDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertStringContainsString(
            'does not support a payment candidate',
            $decision->recommendation
        );

        $this->assertStringContainsString(
            'does not establish that no payment occurred',
            $decision->rationale
        );

        $this->assertStringNotContainsString(
            'client did not pay',
            strtolower(
                $decision->recommendation
            )
        );

        $contextEvidence =
            $decision
                ->evidence
                ->firstWhere(
                    'position',
                    PaymentDecisionEvidence::CONTEXT
                );

        $this->assertNotNull(
            $contextEvidence
        );

        $this->assertSame(
            $this->truthBoundary(),
            $contextEvidence
                ->metadata[
                    'truth_boundary'
                ]
        );
    }

    public function test_weak_unidentified_exact_amount_candidates_are_conditional_with_payer_identity_unresolved(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'weak_unidentified_exact_amount_candidates',
                        anonymousExactAmountCoincidenceCount: 2
                    )
                );

        $this->assertSame(
            PaymentDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertStringContainsString(
            'payer identity remains unresolved',
            $decision->recommendation
        );

        $this->assertSame(
            'payer_identity_unresolved',
            $decision
                ->constraints
                ->first()
                ->code
        );

        $this->assertSame(
            PaymentDecisionConstraint::CONDITION,
            $decision
                ->constraints
                ->first()
                ->type
        );

        $this->assertCount(
            1,
            $decision->missingTruth
        );

        $this->assertStringContainsString(
            'is not established',
            $decision
                ->missingTruth
                ->first()
        );

        $this->assertSame(
            100,
            $decision->confidence
        );
    }

    public function test_missing_bank_evidence_defers(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'bank_evidence_missing'
                    )
                );

        $this->assertDeferredWithBlocker(
            decision: $decision,

            code: 'bank_evidence_missing'
        );
    }

    public function test_incomplete_bank_date_span_defers(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'bank_date_span_incomplete'
                    )
                );

        $this->assertDeferredWithBlocker(
            decision: $decision,

            code: 'bank_date_span_incomplete'
        );
    }

    public function test_missing_invoice_evidence_defers(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'no_invoice_evidence',
                        invoiceCount: 0
                    )
                );

        $this->assertDeferredWithBlocker(
            decision: $decision,

            code: 'invoice_evidence_missing'
        );
    }

    public function test_all_six_established_upstream_states_have_explicit_v1_mapping(): void
    {
        $expected = [
            'supported_payment_candidate_found' => PaymentDecision::RECOMMENDED,

            'no_supported_payment_candidate_found' => PaymentDecision::RECOMMENDED,

            'weak_unidentified_exact_amount_candidates' => PaymentDecision::CONDITIONAL,

            'bank_evidence_missing' => PaymentDecision::DEFERRED,

            'bank_date_span_incomplete' => PaymentDecision::DEFERRED,

            'no_invoice_evidence' => PaymentDecision::DEFERRED,
        ];

        foreach (
            $expected as $state => $status
        ) {
            $decision =
                $this->policy()
                    ->decide(
                        $this->context(
                            state: $state,

                            invoiceCount: $state === 'no_invoice_evidence'
                                    ? 0
                                    : 1,

                            supportedCandidates: $state === 'supported_payment_candidate_found'
                                    ? [
                                        [
                                            'transaction_id' => 'transaction-1',
                                        ],
                                    ]
                                    : [],

                            anonymousExactAmountCoincidenceCount: $state === 'weak_unidentified_exact_amount_candidates'
                                    ? 1
                                    : 0
                        )
                    );

            $this->assertSame(
                $status,
                $decision->status,
                "Unexpected status for {$state}"
            );
        }
    }

    public function test_unknown_upstream_state_fails_closed(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment evidence conclusion readiness policy does not recognise payment evidence state future_unrecognised_state.'
        );

        $this->policy()
            ->decide(
                $this->context(
                    state: 'future_unrecognised_state'
                )
            );
    }

    public function test_exact_amount_coincidence_is_not_promoted_to_payment_identity(): void
    {
        $decision =
            $this->policy()
                ->decide(
                    $this->context(
                        state: 'weak_unidentified_exact_amount_candidates',
                        anonymousExactAmountCoincidenceCount: 4
                    )
                );

        $this->assertSame(
            PaymentDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertStringContainsString(
            'Amount coincidence alone is not payment identity',
            $decision->rationale
        );

        $this->assertStringNotContainsString(
            'payment confirmed',
            strtolower(
                $decision->recommendation
            )
        );
    }

    public function test_policy_preserves_truth_boundary_as_context_for_every_established_state(): void
    {
        foreach (
            [
                'supported_payment_candidate_found',
                'no_supported_payment_candidate_found',
                'weak_unidentified_exact_amount_candidates',
                'bank_evidence_missing',
                'bank_date_span_incomplete',
                'no_invoice_evidence',
            ] as $state
        ) {
            $decision =
                $this->policy()
                    ->decide(
                        $this->context(
                            state: $state,

                            invoiceCount: $state === 'no_invoice_evidence'
                                    ? 0
                                    : 1,

                            supportedCandidates: $state === 'supported_payment_candidate_found'
                                    ? [
                                        [
                                            'transaction_id' => 'transaction-1',
                                        ],
                                    ]
                                    : [],

                            anonymousExactAmountCoincidenceCount: $state === 'weak_unidentified_exact_amount_candidates'
                                    ? 1
                                    : 0
                        )
                    );

            $contextEvidence =
                $decision
                    ->evidence
                    ->firstWhere(
                        'position',
                        PaymentDecisionEvidence::CONTEXT
                    );

            $this->assertNotNull(
                $contextEvidence
            );

            $this->assertSame(
                $this->truthBoundary(),
                $contextEvidence
                    ->metadata[
                        'truth_boundary'
                    ]
            );
        }
    }

    public function test_policy_has_no_mutating_payment_workflow_or_legacy_authority_dependency(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Payment/Decision/PaymentEvidenceConclusionReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'HistoricalPaymentVerificationService',
                'ChronologicalExactPaymentMatchService',
                'RecurringPaymentSequenceMatchService',
                'UniqueExactPaymentMatchService',
                'PaymentAllocationApprovalService',
                'ReconciliationReviewPriorityService',
                'ClientLedgerRiskService',
                'ClientAttentionService',
                'PaymentAllocation::',
                'Client::query',
                'DB::',
                '->save(',
                '->create(',
                '->update(',
                '->delete(',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_policy_does_not_create_collection_chasing_ranking_or_execution_guidance(): void
    {
        $source =
            strtolower(
                file_get_contents(
                    app_path(
                        'Domains/Payment/Decision/PaymentEvidenceConclusionReadinessPolicy.php'
                    )
                )
            );

        foreach (
            [
                'chase the client',
                'send invoice',
                'draft invoice',
                'rank this client',
                'collection action',
                'execute collection',
                'approve this payment',
                'allocate this transaction',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function assertDeferredWithBlocker(
        PaymentDecision $decision,
        string $code,
    ): void {
        $this->assertSame(
            PaymentDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertSame(
            $code,
            $decision
                ->constraints
                ->first()
                ->code
        );

        $this->assertSame(
            PaymentDecisionConstraint::BLOCKING,
            $decision
                ->constraints
                ->first()
                ->type
        );

        $this->assertFalse(
            $decision->missingTruth->isEmpty()
        );
    }

    private function policy(): PaymentEvidenceConclusionReadinessPolicy
    {
        return new PaymentEvidenceConclusionReadinessPolicy;
    }

    private function request(
        string $key = PaymentEvidenceConclusionReadinessPolicy::KEY,
        array $parameters = [],
    ): PaymentDecisionRequest {
        return new PaymentDecisionRequest(
            key: $key,

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            clientId: $this->clientId(),

            parameters: $parameters
        );
    }

    private function context(
        string $state,
        ?PaymentDecisionRequest $request = null,
        int $invoiceCount = 1,
        array $supportedCandidates = [],
        int $anonymousExactAmountCoincidenceCount = 0,
    ): PaymentDecisionContext {
        return new PaymentDecisionContext(
            request: $request
                ?? $this->request(),

            paymentEvidence: new ClientPaymentEvidenceSearchResult(
                clientId: $this->clientId(),

                clientName: 'Exact Payment Client',

                state: $state,

                invoiceCount: $invoiceCount,

                accountingPaid: 0,

                accountingOutstanding: 100,

                canonicalCash: 0,

                confirmedAllocatedPayment: 0,

                allocationUncoveredAmount: 100,

                approvedPaymentCount: 0,

                sourceOutstandingDisagreementCount: 0,

                firstInvoiceAt: $invoiceCount > 0
                        ? '2026-01-01'
                        : null,

                lastInvoiceAt: $invoiceCount > 0
                        ? '2026-01-31'
                        : null,

                bankFirstTransactionAt: $state === 'bank_evidence_missing'
                        ? null
                        : '2025-12-01',

                bankLastTransactionAt: $state === 'bank_evidence_missing'
                        ? null
                        : '2026-02-28',

                bankDateSpanCoversInvoices: ! in_array(
                    $state,
                    [
                        'bank_evidence_missing',
                        'bank_date_span_incomplete',
                    ],
                    true
                ),

                paymentIdentityCount: 0,

                highConfidencePaymentIdentityCount: 0,

                aliases: [],

                directAliasHitCount: 0,

                paymentIdentityHitCount: 0,

                explicitInvoiceReferenceHitCount: 0,

                exactAmountCoincidenceCount: $anonymousExactAmountCoincidenceCount,

                namedOtherExactAmountCoincidenceCount: 0,

                anonymousExactAmountCoincidenceCount: $anonymousExactAmountCoincidenceCount,

                supportedCandidates: $supportedCandidates,

                truthBoundary: $this->truthBoundary()
            ),

            observedAt: CarbonImmutable::parse(
                '2026-09-05 12:00:00'
            )
        );
    }

    private function truthBoundary(): string
    {
        return 'A payment evidence search can establish that no supported receipt candidate was found in the available evidence. It cannot prove that no payment occurred. Amount coincidence alone is not payment identity, and bank date-span coverage does not prove that every source statement or payer identity is complete.';
    }

    private function clientId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }
}
