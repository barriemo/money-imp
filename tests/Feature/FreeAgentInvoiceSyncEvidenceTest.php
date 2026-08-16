<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentInvoiceSyncService;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FreeAgentInvoiceSyncEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_second_invoice_sync_does_not_republish_evidence(): void
    {
        $connection =
            $this->connection();

        $this->clientWithFreeAgentContact(
            $connection
        );

        $payload =
            $this->invoicePayload(
                paidValue: 0,
                dueValue: 90,
                status: 'Open'
            );

        Http::fake([
            '*' => Http::response(
                [
                    'invoices' => [
                        $payload,
                    ],
                ],
                200
            ),
        ]);

        $bus =
            Mockery::mock(
                InvestigationEvidenceBus::class
            );

        $bus
            ->shouldReceive(
                'publish'
            )
            ->once()
            ->with(
                Mockery::on(
                    function (EvidenceChange $change) use ($connection): bool {
                        return
                            $change->domain === 'accounting'
                            && $change->type === 'invoices_changed'
                            && $change->metadata['connection_id']
                                === $connection->id
                            && $change->metadata['records_created'] === 1
                            && $change->metadata['records_updated'] === 0;
                    }
                )
            )
            ->andReturn(
                collect()
            );

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );

        $service =
            app(
                FreeAgentInvoiceSyncService::class
            );

        $first =
            $service->sync(
                $connection
            );

        $this->assertSame(
            1,
            $first->records_created
        );

        $second =
            $service->sync(
                $connection
            );

        $this->assertSame(
            0,
            $second->records_created
        );

        $this->assertSame(
            0,
            $second->records_updated
        );

        $this->assertDatabaseCount(
            'accounting_invoices',
            1
        );

    }

    public function test_changed_invoice_sync_republishes_accounting_evidence(): void
    {
        $connection =
            $this->connection();

        $this->clientWithFreeAgentContact(
            $connection
        );

        $firstPayload =
            $this->invoicePayload(
                paidValue: 0,
                dueValue: 90,
                status: 'Open'
            );

        $secondPayload =
            $this->invoicePayload(
                paidValue: 90,
                dueValue: 0,
                status: 'Paid'
            );

        Http::fakeSequence()
            ->push(
                [
                    'invoices' => [
                        $firstPayload,
                    ],
                ],
                200
            )
            ->push(
                [
                    'invoices' => [
                        $secondPayload,
                    ],
                ],
                200
            );

        $bus =
            Mockery::mock(
                InvestigationEvidenceBus::class
            );

        $bus
            ->shouldReceive(
                'publish'
            )
            ->twice()
            ->andReturn(
                collect()
            );

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );

        $service =
            app(
                FreeAgentInvoiceSyncService::class
            );

        $first =
            $service->sync(
                $connection
            );

        $this->assertSame(
            1,
            $first->records_created
        );

        $second =
            $service->sync(
                $connection
            );

        $this->assertSame(
            1,
            $second->records_updated
        );

        $this->assertDatabaseHas(
            'accounting_invoices',
            [
                'invoice_number' => '2135',
                'status' => 'paid',
                'paid_amount' => 90,
                'outstanding_amount' => 0,
            ]
        );
    }

    public function test_many_changed_invoices_publish_one_evidence_change_for_sync(): void
    {
        $connection =
            $this->connection();

        $this->clientWithFreeAgentContact(
            $connection
        );

        Http::fake([
            '*' => Http::response(
                [
                    'invoices' => [
                        $this->invoicePayload(
                            invoiceId: '2135',
                            reference: '2135'
                        ),

                        $this->invoicePayload(
                            invoiceId: '2136',
                            reference: '2136'
                        ),

                        $this->invoicePayload(
                            invoiceId: '2137',
                            reference: '2137'
                        ),
                    ],
                ],
                200
            ),
        ]);

        $bus =
            Mockery::mock(
                InvestigationEvidenceBus::class
            );

        $bus
            ->shouldReceive(
                'publish'
            )
            ->once()
            ->with(
                Mockery::on(
                    fn (EvidenceChange $change): bool => $change->domain === 'accounting'
                        && $change->type === 'invoices_changed'
                        && $change->metadata['records_created'] === 3
                )
            )
            ->andReturn(
                collect()
            );

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );

        $run =
            app(
                FreeAgentInvoiceSyncService::class
            )->sync(
                $connection
            );

        $this->assertSame(
            3,
            $run->records_created
        );

        $this->assertDatabaseCount(
            'accounting_invoices',
            3
        );
    }

    private function connection(): ExternalConnection
    {
        return ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'test',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()
                ->addHour(),
        ]);
    }

    private function clientWithFreeAgentContact(
        ExternalConnection $connection
    ): Client {
        $client =
            Client::create([
                'name' => 'Peak Renewables',
                'status' => 'active',
            ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'contact',
            'external_id' => 'peak-contact',
            'recordable_type' => Client::class,
            'recordable_id' => $client->id,
            'external_reference' => 'https://api.freeagent.com/v2/contacts/peak-contact',
        ]);

        return $client;
    }

    private function invoicePayload(
        string $invoiceId = '2135',
        string $reference = '2135',
        float $paidValue = 0,
        float $dueValue = 90,
        string $status = 'Open'
    ): array {
        return [
            'url' => 'https://api.freeagent.com/v2/invoices/'
                .$invoiceId,

            'contact' => 'https://api.freeagent.com/v2/contacts/peak-contact',

            'reference' => $reference,

            'status' => $status,

            'dated_on' => '2026-07-30',

            'due_on' => '2026-08-06',

            'currency' => 'GBP',

            'net_value' => 75,

            'sales_tax_value' => 15,

            'total_value' => 90,

            'paid_value' => $paidValue,

            'due_value' => $dueValue,

            'comments' => null,

            'invoice_items' => [
                [
                    'description' => 'Monthly service',
                    'quantity' => 1,
                    'price' => 75,
                    'sales_tax_rate' => 20,
                    'position' => 1,
                ],
            ],
        ];
    }
}
