<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Documents\Services\SupplierDocumentDetectionService;
use App\Domains\Suppliers\Documents\Services\SupplierInvoiceProcessor;
use App\Models\AccountingBill;
use App\Models\ImportBatch;
use App\Models\Supplier;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupplierInvoiceProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_becomes_accounting_bill_with_items(): void
    {
        $supplier = Supplier::create([
            'name' => '20i',
            'status' => 'active',
        ]);
        SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'active' => true,
        ]);

        $batch = ImportBatch::create([
            'source_type' => 'supplier_invoice',
            'provider' => 'supplier_invoice',
            'original_filename' => '20i-invoice.pdf',
            'storage_path' => 'imports/20i-invoice.pdf',
            'status' => 'pending_review',
            'metadata' => [
                'supplier' => '20i',
            ],
        ]);

        $detected = [
            [
                'type' => 'hosting_server',
                'key' => 'vps-17b94a.mvps.stackcp.net',
                'name' => 'vps-17b94a.mvps.stackcp.net',
                'cost' => 8.99,
                'confidence' => 100,
            ],
            [
                'type' => 'storage',
                'key' => 'timeline-storage',
                'name' => 'Cloud Server Timeline Storage',
                'cost' => 1.35,
                'confidence' => 100,
            ],
            [
                'type' => 'hosting_server',
                'key' => 'imp1',
                'name' => 'Imp1',
                'cost' => 89.99,
                'confidence' => 100,
            ],
        ];

        $detector = Mockery::mock(
            SupplierDocumentDetectionService::class
        );

        $detector
            ->shouldReceive('detect')
            ->once()
            ->withArgs(fn (ImportBatch $received): bool => $received->id === $batch->id
            )
            ->andReturn($detected);

        $this->app->instance(
            SupplierDocumentDetectionService::class,
            $detector
        );

        $bill = app(
            SupplierInvoiceProcessor::class
        )->process($batch);

        $this->assertSame(
            $supplier->id,
            $bill->supplier_id
        );

        $this->assertSame(
            'draft',
            $bill->status
        );

        $this->assertEquals(
            100.33,
            (float) $bill->gross_amount
        );

        $this->assertEquals(
            100.33,
            (float) $bill->outstanding_amount
        );

        $this->assertSame(
            $batch->id,
            $bill->metadata['source_import_batch_id']
        );

        $this->assertSame(
            '20i-invoice.pdf',
            $bill->metadata['original_filename']
        );

        $this->assertSame(
            3,
            $bill->metadata['line_count']
        );

        $this->assertCount(
            3,
            $bill->items
        );

        $this->assertEquals(
            100.33,
            (float) $bill->items->sum('gross_amount')
        );

        $this->assertDatabaseCount(
            'accounting_bills',
            1
        );

        $this->assertDatabaseCount(
            'accounting_bill_items',
            3
        );
    }

    public function test_processor_is_idempotent_for_same_import_batch(): void
    {
        $supplier = Supplier::create([
            'name' => '20i',
            'status' => 'active',
        ]);
        SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'active' => true,
        ]);

        $batch = ImportBatch::create([
            'source_type' => 'supplier_invoice',
            'provider' => 'supplier_invoice',
            'original_filename' => '20i-invoice.pdf',
            'storage_path' => 'imports/20i-invoice.pdf',
            'status' => 'pending_review',
            'metadata' => [
                'supplier' => '20i',
            ],
        ]);

        $detected = [
            [
                'type' => 'hosting_server',
                'key' => 'imp1',
                'name' => 'Imp1',
                'cost' => 89.99,
                'confidence' => 100,
            ],
        ];

        $detector = Mockery::mock(
            SupplierDocumentDetectionService::class
        );

        $detector
            ->shouldReceive('detect')
            ->once()
            ->withArgs(fn (ImportBatch $received): bool => $received->id === $batch->id
            )
            ->andReturn($detected);

        $this->app->instance(
            SupplierDocumentDetectionService::class,
            $detector
        );

        $processor = app(
            SupplierInvoiceProcessor::class
        );

        $first = $processor->process($batch);

        $second = $processor->process($batch);

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'accounting_bills',
            1
        );

        $this->assertDatabaseCount(
            'accounting_bill_items',
            1
        );

        $this->assertSame(
            $batch->id,
            $second->metadata['source_import_batch_id']
        );
    }

    public function test_processor_rejects_invoice_without_priced_items(): void
    {
        $supplier = Supplier::create([
            'name' => '20i',
            'status' => 'active',
        ]);
        SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'active' => true,
        ]);

        $batch = ImportBatch::create([
            'source_type' => 'supplier_invoice',
            'provider' => 'supplier_invoice',
            'original_filename' => '20i-invoice.pdf',
            'storage_path' => 'imports/20i-invoice.pdf',
            'status' => 'pending_review',
            'metadata' => [
                'supplier' => '20i',
            ],
        ]);

        $detector = Mockery::mock(
            SupplierDocumentDetectionService::class
        );

        $detector
            ->shouldReceive('detect')
            ->once()
            ->andReturn([
                [
                    'type' => 'hosting_server',
                    'key' => 'imp1',
                    'name' => 'Imp1',
                    'cost' => null,
                    'confidence' => 100,
                ],
            ]);

        $this->app->instance(
            SupplierDocumentDetectionService::class,
            $detector
        );

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'Supplier invoice contained no priced line items.'
        );

        app(
            SupplierInvoiceProcessor::class
        )->process($batch);

        $this->assertDatabaseCount(
            'accounting_bills',
            0
        );
    }

    public function test_supplier_invoice_can_be_processed_through_import_workflow(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'name' => '20i',
            'status' => 'active',
        ]);
        SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'active' => true,
        ]);

        $batch = ImportBatch::create([
            'source_type' => 'supplier_invoice',
            'provider' => 'supplier_invoice',
            'original_filename' => '20i-invoice.pdf',
            'storage_path' => 'imports/20i-invoice.pdf',
            'status' => 'pending_review',
            'metadata' => [
                'supplier' => '20i',
            ],
        ]);

        $detected = [
            [
                'type' => 'hosting_server',
                'key' => 'imp1',
                'name' => 'Imp1',
                'cost' => 89.99,
                'confidence' => 100,
            ],
        ];

        $detector = Mockery::mock(
            SupplierDocumentDetectionService::class
        );

        $detector
            ->shouldReceive('detect')
            ->once()
            ->withArgs(
                fn (ImportBatch $received): bool => $received->id === $batch->id
            )
            ->andReturn($detected);

        $this->app->instance(
            SupplierDocumentDetectionService::class,
            $detector
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'imports.process-supplier-invoice',
                    $batch
                )
            );

        $response
            ->assertRedirect(
                route('imports.index')
            )
            ->assertSessionHas(
                'success'
            );

        $batch->refresh();

        $this->assertSame(
            'completed',
            $batch->status
        );

        $this->assertTrue(
            $batch->metadata['supplier_invoice_processed']
        );

        $this->assertNotEmpty(
            $batch->metadata['accounting_bill_id']
        );

        $bill = AccountingBill::findOrFail(
            $batch->metadata['accounting_bill_id']
        );

        $this->assertSame(
            $supplier->id,
            $bill->supplier_id
        );

        $this->assertSame(
            'draft',
            $bill->status
        );

        $this->assertEquals(
            89.99,
            (float) $bill->gross_amount
        );

        $this->assertSame(
            'pending_review',
            $bill->metadata['processing_status']
        );

        $this->assertDatabaseCount(
            'accounting_bills',
            1
        );

        $this->assertDatabaseCount(
            'accounting_bill_items',
            1
        );
    }

    public function test_supplier_invoice_creates_supplier_assets_from_same_detection_result(): void
    {
        $supplier = Supplier::create([
            'name' => '20i',
            'status' => 'active',
        ]);

        SupplierProfile::create([
            'supplier_name' => '20i',
            'supplier_key' => '20i',
            'active' => true,
        ]);

        $batch = ImportBatch::create([
            'source_type' => 'supplier_invoice',
            'provider' => 'supplier_invoice',
            'original_filename' => '20i-invoice.pdf',
            'storage_path' => 'imports/20i-invoice.pdf',
            'status' => 'pending_review',
            'metadata' => [
                'supplier' => '20i',
            ],
        ]);

        $detected = [
            [
                'type' => 'hosting_server',
                'key' => 'imp1',
                'name' => 'Imp1',
                'cost' => 89.99,
                'confidence' => 100,
            ],
            [
                'type' => 'storage',
                'key' => 'timeline-storage',
                'name' => 'Cloud Server Timeline Storage',
                'cost' => 1.35,
                'confidence' => 100,
            ],
        ];

        $detector = Mockery::mock(
            SupplierDocumentDetectionService::class
        );

        $detector
            ->shouldReceive('detect')
            ->once()
            ->withArgs(
                fn (ImportBatch $received): bool => $received->id === $batch->id
            )
            ->andReturn($detected);

        $this->app->instance(
            SupplierDocumentDetectionService::class,
            $detector
        );

        $bill = app(
            SupplierInvoiceProcessor::class
        )->process($batch);

        $this->assertEquals(
            91.34,
            (float) $bill->gross_amount
        );

        $this->assertDatabaseCount(
            'accounting_bills',
            1
        );

        $this->assertDatabaseCount(
            'accounting_bill_items',
            2
        );

        $this->assertDatabaseCount(
            'supplier_assets',
            2
        );

        $this->assertDatabaseHas(
            'supplier_assets',
            [
                'supplier_profile_id' => SupplierProfile::where(
                    'supplier_key',
                    '20i'
                )->firstOrFail()->id,
                'asset_type' => 'hosting_server',
                'asset_key' => 'imp1',
                'observed_cost' => 89.99,
            ]
        );

        $this->assertDatabaseHas(
            'supplier_assets',
            [
                'asset_type' => 'storage',
                'asset_key' => 'timeline-storage',
                'observed_cost' => 1.35,
            ]
        );
    }
}
