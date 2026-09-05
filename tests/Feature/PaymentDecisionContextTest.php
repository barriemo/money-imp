<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchResult;
use App\Domains\Payment\Decision\PaymentDecisionContext;
use App\Domains\Payment\Decision\PaymentDecisionContextService;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class PaymentDecisionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_requires_an_exact_non_empty_client_subject(): void
    {
        foreach (
            [
                '',
                '   ',
            ] as $clientId
        ) {
            try {
                new PaymentDecisionRequest(
                    key: 'payment-evidence-conclusion',

                    question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

                    clientId: $clientId
                );

                $this->fail(
                    'Empty payment client id was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Payment decision request client id cannot be empty.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_request_parameter_contract_is_bounded(): void
    {
        $client =
            Client::factory()
                ->create();

        $request =
            new PaymentDecisionRequest(
                key: 'payment-evidence-conclusion',

                question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

                clientId: $client->id,

                parameters: [
                    'mode' => 'review',
                    'limit' => 10,
                    'enabled' => true,
                    'optional' => null,
                ]
            );

        $this->assertSame(
            $client->id,
            $request->clientId
        );

        $this->assertSame(
            'review',
            $request->parameters['mode']
        );

        foreach (
            [
                [
                    '' => 'invalid',
                ],
                [
                    'nested' => [
                        'invalid',
                    ],
                ],
            ] as $parameters
        ) {
            try {
                new PaymentDecisionRequest(
                    key: 'payment-evidence-conclusion',

                    question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

                    clientId: $client->id,

                    parameters: $parameters
                );

                $this->fail(
                    'Invalid payment decision parameter contract was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_unknown_client_subject_is_rejected_before_payment_evidence_search(): void
    {
        $request =
            $this->request(
                '00000000-0000-4000-8000-000000000001'
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment decision subject client does not exist.'
        );

        app(
            PaymentDecisionContextService::class
        )->forDecision(
            $request,
            $this->observedAt()
        );
    }

    public function test_context_assembles_payment_evidence_for_the_exact_client_subject(): void
    {
        $client =
            Client::factory()
                ->create([
                    'name' => 'Exact Payment Client',
                ]);

        Client::factory()
            ->create([
                'name' => 'Other Payment Client',
            ]);

        $observedAt =
            $this->observedAt();

        $context =
            app(
                PaymentDecisionContextService::class
            )->forDecision(
                $this->request(
                    $client->id
                ),
                $observedAt
            );

        $this->assertSame(
            $client->id,
            $context->request->clientId
        );

        $this->assertSame(
            $client->id,
            $context->paymentEvidence->clientId
        );

        $this->assertSame(
            'Exact Payment Client',
            $context->paymentEvidence->clientName
        );

        $this->assertSame(
            'no_invoice_evidence',
            $context->paymentEvidence->state
        );

        $this->assertSame(
            0,
            $context->paymentEvidence->invoiceCount
        );

        $this->assertSame(
            [],
            $context->paymentEvidence->supportedCandidates
        );

        $this->assertNotSame(
            '',
            trim(
                $context->paymentEvidence->truthBoundary
            )
        );

        $this->assertTrue(
            $context->observedAt
                ->equalTo(
                    $observedAt
                )
        );
    }

    public function test_context_preserves_upstream_payment_truth_boundary_without_turning_it_into_a_decision(): void
    {
        $client =
            Client::factory()
                ->create();

        $context =
            app(
                PaymentDecisionContextService::class
            )->forDecision(
                $this->request(
                    $client->id
                ),
                $this->observedAt()
            );

        $this->assertStringContainsString(
            'It cannot prove that no payment occurred.',
            $context->paymentEvidence->truthBoundary
        );

        $this->assertStringContainsString(
            'Amount coincidence alone is not payment identity',
            $context->paymentEvidence->truthBoundary
        );

        $reflection =
            new ReflectionClass(
                PaymentDecisionContext::class
            );

        $this->assertFalse(
            $reflection->hasProperty(
                'recommendation'
            )
        );

        $this->assertFalse(
            $reflection->hasProperty(
                'decision'
            )
        );
    }

    public function test_context_rejects_payment_evidence_attributed_to_a_different_client(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment decision context evidence must belong to the requested client.'
        );

        new PaymentDecisionContext(
            request: $this->request(
                '00000000-0000-4000-8000-000000000001'
            ),

            paymentEvidence: $this->paymentEvidence(
                clientId: '00000000-0000-4000-8000-000000000002'
            ),

            observedAt: $this->observedAt()
        );
    }

    public function test_context_requires_upstream_state_and_truth_boundary(): void
    {
        foreach (
            [
                [
                    'state' => '',
                    'truthBoundary' => 'Boundary exists.',
                    'message' => 'Payment decision context evidence state cannot be empty.',
                ],
                [
                    'state' => 'no_invoice_evidence',
                    'truthBoundary' => '',
                    'message' => 'Payment decision context truth boundary cannot be empty.',
                ],
            ] as $case
        ) {
            try {
                new PaymentDecisionContext(
                    request: $this->request(
                        '00000000-0000-4000-8000-000000000001'
                    ),

                    paymentEvidence: $this->paymentEvidence(
                        clientId: '00000000-0000-4000-8000-000000000001',
                        state: $case['state'],
                        truthBoundary: $case['truthBoundary']
                    ),

                    observedAt: $this->observedAt()
                );

                $this->fail(
                    'Invalid upstream payment evidence context was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    $case['message'],
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_context_contract_contains_no_policy_workflow_ranking_or_execution_state(): void
    {
        $reflection =
            new ReflectionClass(
                PaymentDecisionContext::class
            );

        foreach (
            [
                'priority',
                'score',
                'urgency',
                'ranking',
                'recommendation',
                'recommendedAction',
                'decision',
                'allocationId',
                'paymentAllocationId',
                'approvalId',
                'approvedBy',
                'approvedAt',
                'collectionAction',
                'chaseAction',
                'clientRank',
                'riskScore',
                'attentionScore',
                'action',
                'actionId',
                'execution',
                'executedAt',
                'outcomeId',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_context_service_depends_only_on_safe_client_payment_evidence_search(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Payment/Decision/PaymentDecisionContextService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'ClientPaymentEvidenceSearchService',
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
                'recommendedAction',
                'collectionAction',
                'chaseAction',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_context_assembly_is_read_only(): void
    {
        $client =
            Client::factory()
                ->create();

        $before = [
            'clients' => Client::query()->count(),
            'invoices' => AccountingInvoice::query()->count(),
            'bank_transactions' => BankTransaction::query()->count(),
            'payment_allocations' => PaymentAllocation::query()->count(),
        ];

        app(
            PaymentDecisionContextService::class
        )->forDecision(
            $this->request(
                $client->id
            ),
            $this->observedAt()
        );

        $after = [
            'clients' => Client::query()->count(),
            'invoices' => AccountingInvoice::query()->count(),
            'bank_transactions' => BankTransaction::query()->count(),
            'payment_allocations' => PaymentAllocation::query()->count(),
        ];

        $this->assertSame(
            $before,
            $after
        );
    }

    private function request(
        string $clientId
    ): PaymentDecisionRequest {
        return new PaymentDecisionRequest(
            key: 'payment-evidence-conclusion',

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            clientId: $clientId
        );
    }

    private function paymentEvidence(
        string $clientId,
        string $state = 'no_invoice_evidence',
        string $truthBoundary = 'Available evidence is bounded and incomplete conclusions must remain explicit.'
    ): ClientPaymentEvidenceSearchResult {
        return new ClientPaymentEvidenceSearchResult(
            clientId: $clientId,

            clientName: 'Test Client',

            state: $state,

            invoiceCount: 0,

            accountingPaid: 0,

            accountingOutstanding: 0,

            canonicalCash: 0,

            confirmedAllocatedPayment: 0,

            allocationUncoveredAmount: 0,

            approvedPaymentCount: 0,

            sourceOutstandingDisagreementCount: 0,

            firstInvoiceAt: null,

            lastInvoiceAt: null,

            bankFirstTransactionAt: null,

            bankLastTransactionAt: null,

            bankDateSpanCoversInvoices: false,

            paymentIdentityCount: 0,

            highConfidencePaymentIdentityCount: 0,

            aliases: [],

            directAliasHitCount: 0,

            paymentIdentityHitCount: 0,

            explicitInvoiceReferenceHitCount: 0,

            exactAmountCoincidenceCount: 0,

            namedOtherExactAmountCoincidenceCount: 0,

            anonymousExactAmountCoincidenceCount: 0,

            supportedCandidates: [],

            truthBoundary: $truthBoundary
        );
    }

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }
}
