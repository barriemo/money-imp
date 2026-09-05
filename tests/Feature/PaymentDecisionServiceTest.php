<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchResult;
use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentDecisionContext;
use App\Domains\Payment\Decision\PaymentDecisionContextService;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Domains\Payment\Decision\PaymentDecisionService;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PaymentDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            $this->request();

        $context =
            $this->context(
                request: $request,
                state: 'supported_payment_candidate_found',
                supportedCandidates: [
                    [
                        'transaction_id' => 'transaction-1',
                    ],
                ]
            );

        $contexts =
            Mockery::mock(
                PaymentDecisionContextService::class
            );

        $contexts
            ->shouldReceive(
                'forDecision'
            )
            ->once()
            ->with(
                $request
            )
            ->andReturn(
                $context
            );

        $service =
            new PaymentDecisionService(
                $contexts,
                new PaymentEvidenceConclusionReadinessPolicy
            );

        $decision =
            $service->decide(
                $request
            );

        $this->assertSame(
            PaymentDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            PaymentEvidenceConclusionReadinessPolicy::KEY,
            $decision->key
        );

        $this->assertStringContainsString(
            'supports at least one payment candidate',
            $decision->recommendation
        );
    }

    public function test_unsupported_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                PaymentDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new PaymentDecisionService(
                $contexts,
                new PaymentEvidenceConclusionReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new PaymentDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported payment question.',

                clientId: $this->clientId()
            )
        );
    }

    private function request(): PaymentDecisionRequest
    {
        return new PaymentDecisionRequest(
            key: PaymentEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            clientId: $this->clientId()
        );
    }

    private function context(
        PaymentDecisionRequest $request,
        string $state,
        array $supportedCandidates = [],
    ): PaymentDecisionContext {
        return new PaymentDecisionContext(
            request: $request,

            paymentEvidence: new ClientPaymentEvidenceSearchResult(
                clientId: $request->clientId,

                clientName: 'Exact Payment Client',

                state: $state,

                invoiceCount: 1,

                accountingPaid: 0,

                accountingOutstanding: 100,

                canonicalCash: 0,

                confirmedAllocatedPayment: 0,

                allocationUncoveredAmount: 100,

                approvedPaymentCount: 0,

                sourceOutstandingDisagreementCount: 0,

                firstInvoiceAt: '2026-01-01',

                lastInvoiceAt: '2026-01-31',

                bankFirstTransactionAt: '2025-12-01',

                bankLastTransactionAt: '2026-02-28',

                bankDateSpanCoversInvoices: true,

                paymentIdentityCount: 0,

                highConfidencePaymentIdentityCount: 0,

                aliases: [],

                directAliasHitCount: 0,

                paymentIdentityHitCount: 0,

                explicitInvoiceReferenceHitCount: 0,

                exactAmountCoincidenceCount: 0,

                namedOtherExactAmountCoincidenceCount: 0,

                anonymousExactAmountCoincidenceCount: 0,

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
