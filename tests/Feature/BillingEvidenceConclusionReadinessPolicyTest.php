<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingDecisionConstraint;
use App\Domains\Billing\Decision\BillingDecisionContext;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;
use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class BillingEvidenceConclusionReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_billing_evidence_readiness(): void
    {
        $policy =
            new BillingEvidenceConclusionReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request()
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'unsupported-billing-question'
                )
            )
        );
    }

    public function test_policy_rejects_parameters(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing evidence conclusion readiness policy does not accept parameters.'
        );

        $context =
            $this->context(
                request: new BillingDecisionRequest(
                    key: 'billing-evidence-readiness',

                    question: $this->question(),

                    clientServiceId: $this->clientServiceId(),

                    parameters: [
                        'amount' => 100,
                    ]
                )
            );

        (new BillingEvidenceConclusionReadinessPolicy)
            ->decide(
                $context
            );
    }

    public function test_no_canonical_observed_billing_supports_only_bounded_negative_conclusion(): void
    {
        $decision =
            $this->decide(
                $this->context(
                    observedBilling: null
                )
            );

        $this->assertSame(
            BillingDecision::STATUS_RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringContainsString(
            'no canonical observed billing is established',
            strtolower(
                $decision->recommendation
            )
        );

        $this->assertStringContainsString(
            'does not establish that no billing obligation exists',
            strtolower(
                $decision->rationale
            )
        );

        $this->assertStringNotContainsString(
            'nothing is owed',
            strtolower(
                $decision->recommendation
            )
        );

        $this->assertTrue(
            $decision->constraints
                ->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth
                ->isEmpty()
        );
    }

    public function test_current_recurring_canonical_evidence_supports_bounded_current_observed_billing_conclusion(): void
    {
        $decision =
            $this->decide(
                $this->context(
                    observedBilling: $this->observedBilling(
                        cadence: 'monthly',
                        cadenceConfidence: 82,
                        freshness: 'current',
                        recurringEvidence: true,
                        currentMonthlyEquivalent: 100.00
                    )
                )
            );

        $this->assertSame(
            BillingDecision::STATUS_RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertStringContainsString(
            'current recurring canonical billing evidence',
            strtolower(
                $decision->recommendation
            )
        );

        $this->assertStringContainsString(
            '100.00',
            $decision->recommendation
        );

        $this->assertStringContainsString(
            'not a statement of contractual obligation',
            strtolower(
                $decision->rationale
            )
        );

        $support =
            $decision->evidence
                ->first(
                    fn ($item) => $item->position ===
                        'supports'
                );

        $this->assertNotNull(
            $support
        );

        $this->assertSame(
            82,
            $support->metadata[
                'cadence_confidence'
            ]
        );

        $this->assertSame(
            100,
            $support->confidence
        );
    }

    public function test_one_off_observed_billing_is_conditional_and_does_not_become_recurring_truth(): void
    {
        $decision =
            $this->decide(
                $this->context(
                    observedBilling: $this->observedBilling(
                        cadence: 'one_off',
                        cadenceConfidence: 70,
                        freshness: 'recently_observed',
                        recurringEvidence: false,
                        currentMonthlyEquivalent: null,
                        evidenceCount: 1,
                        invoiceItemIds: [
                            'invoice-item-1',
                        ]
                    )
                )
            );

        $this->assertSame(
            BillingDecision::STATUS_CONDITIONAL,
            $decision->status
        );

        $this->assertStringContainsString(
            'recurring billing is not established',
            strtolower(
                $decision->recommendation
            )
        );

        $this->assertTrue(
            $decision->constraints
                ->contains(
                    fn (
                        BillingDecisionConstraint $constraint
                    ): bool => $constraint->key
                        === 'recurring-billing-not-established'
                )
        );

        $this->assertNotEmpty(
            $decision->missingTruth
        );
    }

    public function test_unknown_cadence_observations_remain_conditional(): void
    {
        $decision =
            $this->decide(
                $this->context(
                    observedBilling: $this->observedBilling(
                        cadence: 'unknown',
                        cadenceConfidence: 60,
                        freshness: 'recently_observed',
                        recurringEvidence: false,
                        currentMonthlyEquivalent: null
                    )
                )
            );

        $this->assertSame(
            BillingDecision::STATUS_CONDITIONAL,
            $decision->status
        );

        $this->assertStringContainsString(
            'recurring billing is not established',
            strtolower(
                $decision->recommendation
            )
        );
    }

    public function test_non_current_recurring_evidence_is_conditional_not_current_billing_truth(): void
    {
        foreach (
            [
                'recently_observed',
                'stale',
                'historical',
            ] as $freshness
        ) {
            $decision =
                $this->decide(
                    $this->context(
                        observedBilling: $this->observedBilling(
                            cadence: 'monthly',
                            cadenceConfidence: 90,
                            freshness: $freshness,
                            recurringEvidence: true,
                            currentMonthlyEquivalent: null
                        )
                    )
                );

            $this->assertSame(
                BillingDecision::STATUS_CONDITIONAL,
                $decision->status
            );

            $this->assertStringContainsString(
                'current recurring billing evidence is not established',
                strtolower(
                    $decision->recommendation
                )
            );

            $this->assertTrue(
                $decision->constraints
                    ->contains(
                        fn (
                            BillingDecisionConstraint $constraint
                        ): bool => $constraint->key
                            === 'current-recurring-billing-not-established'
                    )
            );
        }
    }

    public function test_policy_fails_closed_for_impossible_canonical_states(): void
    {
        $cases = [
            $this->observedBilling(
                cadence: 'weekly',
                cadenceConfidence: 100,
                freshness: 'current',
                recurringEvidence: true,
                currentMonthlyEquivalent: 100.00
            ),

            $this->observedBilling(
                cadence: 'one_off',
                cadenceConfidence: 70,
                freshness: 'recently_observed',
                recurringEvidence: true,
                currentMonthlyEquivalent: null
            ),

            $this->observedBilling(
                cadence: 'monthly',
                cadenceConfidence: 79,
                freshness: 'current',
                recurringEvidence: true,
                currentMonthlyEquivalent: 100.00
            ),

            $this->observedBilling(
                cadence: 'monthly',
                cadenceConfidence: 90,
                freshness: 'current',
                recurringEvidence: true,
                currentMonthlyEquivalent: null
            ),

            $this->observedBilling(
                cadence: 'monthly',
                cadenceConfidence: 90,
                freshness: 'stale',
                recurringEvidence: true,
                currentMonthlyEquivalent: 100.00
            ),

            $this->observedBilling(
                cadence: 'one_off',
                cadenceConfidence: 70,
                freshness: 'current',
                recurringEvidence: false,
                currentMonthlyEquivalent: null
            ),
        ];

        foreach ($cases as $observed) {
            try {
                $this->decide(
                    $this->context(
                        observedBilling: $observed
                    )
                );

                $this->fail(
                    'Impossible canonical Billing state was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_policy_fails_closed_when_canonical_dto_has_no_evidence(): void
    {
        foreach (
            [
                $this->observedBilling(
                    evidenceCount: 0
                ),
                $this->observedBilling(
                    invoiceItemIds: []
                ),
            ] as $observed
        ) {
            try {
                $this->decide(
                    $this->context(
                        observedBilling: $observed
                    )
                );

                $this->fail(
                    'Canonical Billing DTO without evidence was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_policy_contains_no_invoice_execution_ranking_or_monthly_audit_authority(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Billing/Decision/BillingEvidenceConclusionReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'MonthlyBillingAuditService',
                'BulkDraftInvoiceService',
                'BulkInvoiceSendService',
                'FreeAgentDraftInvoiceService',
                'FreeAgentInvoiceSendService',
                'WorkInvoiceDraftService',
                'PaymentAllocationApprovalService',
                'median(',
                'underbilled',
                '0.80',
                'priority',
                'ranking',
                'clientRank',
                'riskScore',
                'recommendedAction',
                'Invoice::create',
                'AccountingInvoice::create',
                '->save(',
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

    public function test_policy_preserves_observed_billing_vs_obligation_boundary(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Billing/Decision/BillingEvidenceConclusionReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'does not establish that no billing obligation exists',
            $source
        );

        $this->assertStringContainsString(
            'not a statement of contractual obligation',
            $source
        );

        $this->assertStringContainsString(
            'does not by itself establish a recurring billing obligation',
            $source
        );

        $this->assertStringNotContainsString(
            'should invoice',
            strtolower(
                $source
            )
        );

        $this->assertStringNotContainsString(
            'nothing is owed',
            strtolower(
                $source
            )
        );
    }

    private function decide(
        BillingDecisionContext $context
    ): BillingDecision {
        return (
            new BillingEvidenceConclusionReadinessPolicy
        )->decide(
            $context
        );
    }

    private function context(
        ?CanonicalServiceObservedBilling $observedBilling = null,
        ?BillingDecisionRequest $request = null
    ): BillingDecisionContext {
        return new BillingDecisionContext(
            request: $request
                ?? $this->request(),

            clientId: $this->clientId(),

            clientName: 'Billing Client',

            serviceName: 'Website Hosting',

            serviceStatus: 'active',

            observedBilling: $observedBilling,

            truthBoundary: BillingDecisionContext::TRUTH_BOUNDARY,

            observedAt: CarbonImmutable::parse(
                '2026-09-05 12:00:00'
            )
        );
    }

    private function request(
        string $key = 'billing-evidence-readiness'
    ): BillingDecisionRequest {
        return new BillingDecisionRequest(
            key: $key,

            question: $this->question(),

            clientServiceId: $this->clientServiceId()
        );
    }

    private function question(): string
    {
        return 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?';
    }

    private function observedBilling(
        string $cadence = 'monthly',
        int $cadenceConfidence = 90,
        string $freshness = 'current',
        bool $recurringEvidence = true,
        ?float $currentMonthlyEquivalent = 100.00,
        int $evidenceCount = 2,
        array $invoiceItemIds = [
            'invoice-item-1',
            'invoice-item-2',
        ],
    ): CanonicalServiceObservedBilling {
        return new CanonicalServiceObservedBilling(
            clientServiceId: $this->clientServiceId(),

            clientId: $this->clientId(),

            clientName: 'Billing Client',

            serviceName: 'Website Hosting',

            serviceStatus: 'active',

            evidenceCount: $evidenceCount,

            invoiceItemIds: $invoiceItemIds,

            signedObservedNet: 200.00,

            latestObservedUnitPrice: 100.00,

            firstObservedOn: '2026-06-05',

            lastObservedOn: '2026-07-05',

            cadence: $cadence,

            monthlyEquivalent: $cadence === 'annual'
                    ? 100.00
                    : (
                        $cadence === 'monthly'
                            ? 100.00
                            : 0.00
                    ),

            cadenceConfidence: $cadenceConfidence,

            daysSinceLastObservation: 62,

            freshness: $freshness,

            recurringEvidence: $recurringEvidence,

            currentMonthlyEquivalent: $currentMonthlyEquivalent
        );
    }

    private function clientServiceId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }

    private function clientId(): string
    {
        return '00000000-0000-4000-8000-000000000010';
    }
}
