<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\Delivery\Decision\DeliveryDecisionContext;
use App\Domains\Delivery\Decision\DeliveryDecisionContextService;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

class DeliveryDecisionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_inert_validated_client_subject_input(): void
    {
        $request =
            new DeliveryDecisionRequest(
                key: 'delivery_subject',
                question: 'What delivery truth is available for this client?',
                clientId: 'client-123',
                parameters: [
                    'note' => 'context only',
                    'attempt' => 1,
                    'optional' => null,
                ]
            );

        $this->assertSame(
            'delivery_subject',
            $request->key
        );

        $this->assertSame(
            'What delivery truth is available for this client?',
            $request->question
        );

        $this->assertSame(
            'client-123',
            $request->clientId
        );

        $this->assertSame(
            'context only',
            $request->parameters['note']
        );
    }

    public function test_request_rejects_invalid_subject_and_parameters(): void
    {
        $cases = [
            fn () => new DeliveryDecisionRequest(
                key: '',
                question: 'Question.',
                clientId: 'client-1'
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: '',
                clientId: 'client-1'
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: ''
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                parameters: [
                    '' => 1,
                ]
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                parameters: [
                    'object' => new stdClass,
                ]
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                parameters: [
                    'nested' => [
                        'not' => 'allowed',
                    ],
                ]
            ),

            fn () => new DeliveryDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                parameters: [
                    'amount' => INF,
                ]
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();

                $this->fail(
                    'Expected invalid delivery decision request to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_context_requires_exact_client_truth_identity(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new DeliveryDecisionContext(
            request: $this->request(
                clientId: 'client-1'
            ),
            deliveryTruth: $this->truth(
                clientId: 'client-2'
            ),
            hasRecordedDeliveryEvidence: false,
            observedAt: CarbonImmutable::parse(
                '2026-09-04 23:30:00'
            )
        );
    }

    public function test_context_requires_evidence_presence_to_match_worklog_truth(): void
    {
        $cases = [
            [
                'truth' => $this->truth(
                    workLogCount: 0
                ),
                'presence' => true,
            ],
            [
                'truth' => $this->truth(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0
                ),
                'presence' => false,
            ],
        ];

        foreach ($cases as $case) {
            try {
                new DeliveryDecisionContext(
                    request: $this->request(),
                    deliveryTruth: $case['truth'],
                    hasRecordedDeliveryEvidence: $case['presence'],
                    observedAt: CarbonImmutable::parse(
                        '2026-09-04 23:30:00'
                    )
                );

                $this->fail(
                    'Expected delivery evidence-presence mismatch to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_zero_values_with_no_worklogs_remain_explicit_evidence_absence(): void
    {
        $context =
            new DeliveryDecisionContext(
                request: $this->request(),
                deliveryTruth: $this->truth(),
                hasRecordedDeliveryEvidence: false,
                observedAt: CarbonImmutable::parse(
                    '2026-09-04 23:30:00'
                )
            );

        $this->assertFalse(
            $context->hasRecordedDeliveryEvidence
        );

        $this->assertSame(
            0,
            $context->deliveryTruth->workLogCount
        );

        $this->assertSame(
            0.0,
            $context->deliveryTruth->commercialValue
        );

        $this->assertSame(
            0.0,
            $context
                ->deliveryTruth
                ->uninvoicedCommercialValue
        );

        $this->assertSame(
            0,
            $context
                ->deliveryTruth
                ->invoiceLinkageConfidence
        );
    }

    public function test_zero_invoice_linkage_confidence_does_not_mean_no_delivery_evidence(): void
    {
        $context =
            new DeliveryDecisionContext(
                request: $this->request(),
                deliveryTruth: $this->truth(
                    workLogCount: 1,
                    uninvoicedWorkLogCount: 1,
                    commercialValue: 95.0,
                    uninvoicedCommercialValue: 95.0,
                    invoiceLinkageConfidence: 0
                ),
                hasRecordedDeliveryEvidence: true,
                observedAt: CarbonImmutable::parse(
                    '2026-09-04 23:30:00'
                )
            );

        $this->assertTrue(
            $context->hasRecordedDeliveryEvidence
        );

        $this->assertSame(
            0,
            $context
                ->deliveryTruth
                ->invoiceLinkageConfidence
        );
    }

    public function test_context_service_rejects_unknown_client(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        app(
            DeliveryDecisionContextService::class
        )->forDecision(
            request: $this->request(
                clientId: 'missing-client'
            ),
            observedAt: CarbonImmutable::parse(
                '2026-09-04 23:30:00'
            )
        );
    }

    public function test_context_service_preserves_missing_delivery_evidence_without_inventing_health(): void
    {
        $client =
            $this->client(
                'No Evidence Client'
            );

        $context =
            app(
                DeliveryDecisionContextService::class
            )->forDecision(
                request: $this->request(
                    clientId: (string) $client->id
                ),
                observedAt: CarbonImmutable::parse(
                    '2026-09-04 23:30:00'
                )
            );

        $this->assertSame(
            (string) $client->id,
            $context
                ->deliveryTruth
                ->clientId
        );

        $this->assertSame(
            0,
            $context
                ->deliveryTruth
                ->workLogCount
        );

        $this->assertFalse(
            $context->hasRecordedDeliveryEvidence
        );

        $this->assertSame(
            0,
            $context
                ->deliveryTruth
                ->invoiceLinkageConfidence
        );
    }

    public function test_context_service_uses_worklog_presence_not_linkage_confidence_as_evidence_presence(): void
    {
        $client =
            $this->client(
                'Recorded Work Client'
            );

        WorkLog::query()
            ->create([
                'client_id' => $client->id,

                'user_id' => User::factory()->create()->id,

                'description' => 'Recorded delivery evidence.',

                'minutes' => 60,

                'performed_at' => '2026-09-04 10:00:00',

                'billing_hint' => 'billable',

                'commercial_status' => 'unreviewed',

                'rate_snapshot' => 95.00,

                'commercial_value' => 95.00,
            ]);

        $context =
            app(
                DeliveryDecisionContextService::class
            )->forDecision(
                request: $this->request(
                    clientId: (string) $client->id
                ),
                observedAt: CarbonImmutable::parse(
                    '2026-09-04 23:30:00'
                )
            );

        $this->assertTrue(
            $context->hasRecordedDeliveryEvidence
        );

        $this->assertSame(
            1,
            $context
                ->deliveryTruth
                ->workLogCount
        );

        $this->assertSame(
            1,
            $context
                ->deliveryTruth
                ->uninvoicedWorkLogCount
        );

        $this->assertSame(
            95.0,
            $context
                ->deliveryTruth
                ->uninvoicedCommercialValue
        );

        /*
         * Nothing is invoice-linked, so linkage coverage is zero.
         * That must remain distinct from evidence presence.
         */
        $this->assertSame(
            0,
            $context
                ->deliveryTruth
                ->invoiceLinkageConfidence
        );
    }

    public function test_context_contract_contains_no_policy_priority_or_execution_state(): void
    {
        $reflection =
            new ReflectionClass(
                DeliveryDecisionContext::class
            );

        foreach (
            [
                'recommendation',
                'rationale',
                'confidence',
                'priority',
                'score',
                'urgency',
                'ranking',
                'execution',
                'actionId',
                'outcomeId',
                'project',
                'deliverable',
            ] as $property
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $property
                ),
                sprintf(
                    'Delivery context must not expose %s.',
                    $property
                )
            );
        }
    }

    private function request(
        string $clientId = 'client-1'
    ): DeliveryDecisionRequest {
        return new DeliveryDecisionRequest(
            key: 'delivery_context',
            question: 'What delivery truth is available for this client?',
            clientId: $clientId
        );
    }

    private function truth(
        string $clientId = 'client-1',
        int $workLogCount = 0,
        int $invoicedWorkLogCount = 0,
        int $uninvoicedWorkLogCount = 0,
        float $commercialValue = 0.0,
        float $invoicedCommercialValue = 0.0,
        float $uninvoicedCommercialValue = 0.0,
        int $invoiceLinkageConfidence = 0
    ): DeliveryTruth {
        return new DeliveryTruth(
            clientId: $clientId,
            client: 'Delivery Client',
            workLogCount: $workLogCount,
            invoicedWorkLogCount: $invoicedWorkLogCount,
            uninvoicedWorkLogCount: $uninvoicedWorkLogCount,
            commercialValue: $commercialValue,
            invoicedCommercialValue: $invoicedCommercialValue,
            uninvoicedCommercialValue: $uninvoicedCommercialValue,
            invoiceLinkageConfidence: $invoiceLinkageConfidence
        );
    }

    private function client(
        string $name
    ): Client {
        return Client::query()
            ->create([
                'name' => $name,
                'status' => 'active',
                'currency' => 'GBP',
            ]);
    }
}
