<?php

namespace Tests\Feature;

use App\Domains\Accounting\Actions\AllocateBillItemToProject;
use App\Models\AccountingBill;
use App\Models\AccountingBillItem;
use App\Models\CostAllocation;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_item_can_be_allocated_to_a_project(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'name' => '20i',
            'type' => 'supplier',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'bill_number' => '20I-001',
            'status' => 'outstanding',
            'gross_amount' => 120,
            'outstanding_amount' => 120,
        ]);

        $item = AccountingBillItem::create([
            'accounting_bill_id' => $bill->id,
            'description' => 'Hosting',
            'quantity' => 1,
            'unit_cost' => 120,
            'net_amount' => 100,
            'tax_amount' => 20,
            'gross_amount' => 120,
        ]);

        $project = Project::create([
            'name' => 'Visit Dundee',
        ]);

        $allocation = app(AllocateBillItemToProject::class)->execute(
            $item,
            $project,
            120,
            $user
        );

        $this->assertSame($project->id, $allocation->project_id);
        $this->assertSame('120.00', $allocation->amount);
        $this->assertSame('project', $allocation->allocation_type);
        $this->assertSame('100.0000', $allocation->allocation_percent);
    }

    public function test_bill_item_can_be_split_across_projects(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'name' => 'Shared Supplier',
            'type' => 'supplier',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'bill_number' => 'SHARED-001',
            'status' => 'outstanding',
            'gross_amount' => 100,
            'outstanding_amount' => 100,
        ]);

        $item = AccountingBillItem::create([
            'accounting_bill_id' => $bill->id,
            'description' => 'Shared hosting',
            'quantity' => 1,
            'unit_cost' => 100,
            'net_amount' => 83.33,
            'tax_amount' => 16.67,
            'gross_amount' => 100,
        ]);

        $visitDundee = Project::create([
            'name' => 'Visit Dundee',
        ]);

        $plz = Project::create([
            'name' => 'PLZ',
        ]);

        app(AllocateBillItemToProject::class)->execute(
            $item,
            $visitDundee,
            60,
            $user
        );

        app(AllocateBillItemToProject::class)->execute(
            $item,
            $plz,
            40,
            $user
        );

        $this->assertEquals(
            100.00,
            (float) $item->costAllocations()->sum('amount')
        );

        $this->assertEquals(
            60.00,
            (float) $visitDundee->costAllocations()->sum('amount')
        );

        $this->assertEquals(
            40.00,
            (float) $plz->costAllocations()->sum('amount')
        );

        $this->assertSame(2, CostAllocation::count());
    }

    public function test_project_allocation_cannot_exceed_remaining_bill_item_value(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::create([
            'name' => 'Supplier',
            'type' => 'supplier',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $supplier->id,
            'bill_number' => 'OVER-001',
            'status' => 'outstanding',
            'gross_amount' => 100,
            'outstanding_amount' => 100,
        ]);

        $item = AccountingBillItem::create([
            'accounting_bill_id' => $bill->id,
            'description' => 'Development',
            'quantity' => 1,
            'unit_cost' => 100,
            'net_amount' => 100,
            'gross_amount' => 100,
        ]);

        $project = Project::create([
            'name' => 'Project A',
        ]);

        app(AllocateBillItemToProject::class)->execute(
            $item,
            $project,
            75,
            $user
        );

        $this->expectException(ValidationException::class);

        app(AllocateBillItemToProject::class)->execute(
            $item,
            $project,
            30,
            $user
        );
    }
}
