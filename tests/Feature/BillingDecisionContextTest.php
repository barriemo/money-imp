<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecisionContext;
use App\Domains\Billing\Decision\BillingDecisionContextService;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class BillingDecisionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_requires_an_exact_non_empty_client_service_subject(): void
    {
        foreach (
            [
                '',
                '   ',
            ] as $clientServiceId
        ) {
            try {
                new BillingDecisionRequest(
                    key: 'billing-evidence-readiness',

                    question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

                    clientServiceId: $clientServiceId
                );

                $this->fail(
                    'Empty Billing client service id was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Billing decision request client service id cannot be empty.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_request_parameter_contract_is_bounded(): void
    {
        [, $service] =
            $this->service();

        $request =
            new BillingDecisionRequest(
                key: 'billing-evidence-readiness',

                question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

                clientServiceId: $service->id,

                parameters: [
                    'mode' => 'review',
                    'limit' => 10,
                    'enabled' => true,
                    'optional' => null,
                ]
            );

        $this->assertSame(
            $service->id,
            $request->clientServiceId
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
                new BillingDecisionRequest(
                    key: 'billing-evidence-readiness',

                    question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

                    clientServiceId: $service->id,

                    parameters: $parameters
                );

                $this->fail(
                    'Invalid Billing decision parameter contract was accepted.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_client_service_subject_uses_uuid_identity(): void
    {
        [, $service] =
            $this->service();

        $this->assertTrue(
            Str::isUuid(
                (string) $service->id
            )
        );

        $context =
            app(
                BillingDecisionContextService::class
            )->forDecision(
                $this->request(
                    $service->id
                ),
                $this->observedAt()
            );

        $this->assertSame(
            $service->id,
            $context->request->clientServiceId
        );
    }

    public function test_unknown_client_service_subject_is_rejected_before_context_is_returned(): void
    {
        $request =
            $this->request(
                '00000000-0000-4000-8000-000000000001'
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing decision subject client service does not exist.'
        );

        app(
            BillingDecisionContextService::class
        )->forDecision(
            $request,
            $this->observedAt()
        );
    }

    public function test_context_preserves_exact_subject_when_no_canonical_observed_billing_exists(): void
    {
        [$client, $service] =
            $this->service(
                clientName: 'Exact Billing Client',
                serviceName: 'Exact Hosting Service'
            );

        $context =
            app(
                BillingDecisionContextService::class
            )->forDecision(
                $this->request(
                    $service->id
                ),
                $this->observedAt()
            );

        $this->assertSame(
            $service->id,
            $context->request->clientServiceId
        );

        $this->assertSame(
            $client->id,
            $context->clientId
        );

        $this->assertSame(
            'Exact Billing Client',
            $context->clientName
        );

        $this->assertSame(
            'Exact Hosting Service',
            $context->serviceName
        );

        $this->assertSame(
            'active',
            $context->serviceStatus
        );

        $this->assertNull(
            $context->observedBilling
        );

        $this->assertStringContainsString(
            'No canonical observed billing does not mean no billing obligation exists.',
            $context->truthBoundary
        );
    }

    public function test_context_assembles_canonical_observed_billing_for_only_the_exact_client_service(): void
    {
        [$client, $service] =
            $this->service(
                serviceName: 'Primary Hosting'
            );

        [, $otherService] =
            $this->service(
                serviceName: 'Other Hosting'
            );

        $first =
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: '2026-06-05',
                amount: 100.00
            );

        $second =
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: '2026-07-05',
                amount: 100.00
            );

        $otherClient =
            $otherService->client;

        $other =
            $this->invoiceItem(
                client: $otherClient,
                service: $otherService,
                date: '2026-07-05',
                amount: 999.00
            );

        $observedAt =
            CarbonImmutable::parse(
                '2026-07-10 12:00:00'
            );

        $context =
            app(
                BillingDecisionContextService::class
            )->forDecision(
                $this->request(
                    $service->id
                ),
                $observedAt
            );

        $this->assertNotNull(
            $context->observedBilling
        );

        $this->assertSame(
            $service->id,
            $context->observedBilling
                ->clientServiceId
        );

        $this->assertSame(
            $client->id,
            $context->observedBilling
                ->clientId
        );

        $this->assertSame(
            2,
            $context->observedBilling
                ->evidenceCount
        );

        $this->assertContains(
            $first->id,
            $context->observedBilling
                ->invoiceItemIds
        );

        $this->assertContains(
            $second->id,
            $context->observedBilling
                ->invoiceItemIds
        );

        $this->assertNotContains(
            $other->id,
            $context->observedBilling
                ->invoiceItemIds
        );

        $this->assertSame(
            100.00,
            $context->observedBilling
                ->latestObservedUnitPrice
        );

        $this->assertTrue(
            $context->observedAt
                ->equalTo(
                    $observedAt
                )
        );
    }

    public function test_context_preserves_canonical_truth_without_turning_it_into_a_decision(): void
    {
        [, $service] =
            $this->service();

        $context =
            app(
                BillingDecisionContextService::class
            )->forDecision(
                $this->request(
                    $service->id
                ),
                $this->observedAt()
            );

        $this->assertStringContainsString(
            'does not by itself establish what should be billed',
            $context->truthBoundary
        );

        $this->assertStringContainsString(
            'No canonical observed billing does not mean no billing obligation exists.',
            $context->truthBoundary
        );

        $reflection =
            new ReflectionClass(
                BillingDecisionContext::class
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

    public function test_context_rejects_canonical_billing_for_a_different_client_service(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing decision context observed billing must belong to the requested client service.'
        );

        new BillingDecisionContext(
            request: $this->request(
                '00000000-0000-4000-8000-000000000001'
            ),

            clientId: '00000000-0000-4000-8000-000000000010',

            clientName: 'Test Client',

            serviceName: 'Test Service',

            serviceStatus: 'active',

            observedBilling: $this->observedBilling(
                clientServiceId: '00000000-0000-4000-8000-000000000002',

                clientId: '00000000-0000-4000-8000-000000000010'
            ),

            truthBoundary: BillingDecisionContext::TRUTH_BOUNDARY,

            observedAt: $this->observedAt()
        );
    }

    public function test_context_rejects_canonical_billing_for_a_different_client(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing decision context observed billing must belong to the subject client.'
        );

        new BillingDecisionContext(
            request: $this->request(
                '00000000-0000-4000-8000-000000000001'
            ),

            clientId: '00000000-0000-4000-8000-000000000010',

            clientName: 'Test Client',

            serviceName: 'Test Service',

            serviceStatus: 'active',

            observedBilling: $this->observedBilling(
                clientServiceId: '00000000-0000-4000-8000-000000000001',

                clientId: '00000000-0000-4000-8000-000000000020'
            ),

            truthBoundary: BillingDecisionContext::TRUTH_BOUNDARY,

            observedAt: $this->observedAt()
        );
    }

    public function test_context_contract_contains_no_policy_workflow_ranking_or_execution_state(): void
    {
        $reflection =
            new ReflectionClass(
                BillingDecisionContext::class
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
                'invoiceId',
                'invoiceDraftId',
                'draftInvoiceId',
                'sendInvoice',
                'invoiceSendId',
                'freeAgentInvoiceId',
                'billingRunId',
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

    public function test_context_service_depends_only_on_exact_subject_and_safe_canonical_billing_read_model(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Billing/Decision/BillingDecisionContextService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        $this->assertStringContainsString(
            'ClientService',
            $source
        );

        $this->assertStringContainsString(
            'CanonicalServiceObservedBillingService',
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
                'median(',
                'underbilled',
                '0.80',
                'recommendedAction',
                'priority',
                'ranking',
                'Invoice::create',
                'AccountingInvoice::create',
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
        [$client, $service] =
            $this->service();

        $this->invoiceItem(
            client: $client,
            service: $service,
            date: '2026-06-05',
            amount: 100.00
        );

        $this->invoiceItem(
            client: $client,
            service: $service,
            date: '2026-07-05',
            amount: 100.00
        );

        $before = [
            'clients' => Client::query()->count(),

            'client_services' => ClientService::query()
                ->withTrashed()
                ->count(),

            'invoices' => AccountingInvoice::query()
                ->count(),

            'invoice_items' => AccountingInvoiceItem::query()
                ->count(),
        ];

        app(
            BillingDecisionContextService::class
        )->forDecision(
            $this->request(
                $service->id
            ),
            CarbonImmutable::parse(
                '2026-07-10 12:00:00'
            )
        );

        $after = [
            'clients' => Client::query()->count(),

            'client_services' => ClientService::query()
                ->withTrashed()
                ->count(),

            'invoices' => AccountingInvoice::query()
                ->count(),

            'invoice_items' => AccountingInvoiceItem::query()
                ->count(),
        ];

        $this->assertSame(
            $before,
            $after
        );
    }

    private function request(
        string $clientServiceId
    ): BillingDecisionRequest {
        return new BillingDecisionRequest(
            key: 'billing-evidence-readiness',

            question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

            clientServiceId: $clientServiceId
        );
    }

    private function service(
        string $clientName = 'Billing Client',
        string $serviceName = 'Website Hosting'
    ): array {
        $client =
            Client::factory()
                ->create([
                    'name' => $clientName,
                ]);

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => $serviceName,
                'type' => 'service',
                'status' => 'active',
            ]);

        return [
            $client,
            $service,
        ];
    }

    private function invoiceItem(
        Client $client,
        ClientService $service,
        string $date,
        float $amount
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => (string) str()->uuid(),
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'client_service_id' => $service->id,

            'description' => 'Observed billing evidence',

            'quantity' => 1,

            'unit_price' => $amount,

            'net_amount' => $amount,
        ]);
    }

    private function observedBilling(
        string $clientServiceId,
        string $clientId
    ): CanonicalServiceObservedBilling {
        return new CanonicalServiceObservedBilling(
            clientServiceId: $clientServiceId,

            clientId: $clientId,

            clientName: 'Test Client',

            serviceName: 'Test Service',

            serviceStatus: 'active',

            evidenceCount: 2,

            invoiceItemIds: [
                'invoice-item-1',
                'invoice-item-2',
            ],

            signedObservedNet: 200.00,

            latestObservedUnitPrice: 100.00,

            firstObservedOn: '2026-06-05',

            lastObservedOn: '2026-07-05',

            cadence: 'monthly',

            monthlyEquivalent: 100.00,

            cadenceConfidence: 100,

            daysSinceLastObservation: 5,

            freshness: 'current',

            recurringEvidence: true,

            currentMonthlyEquivalent: 100.00
        );
    }

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }
}
