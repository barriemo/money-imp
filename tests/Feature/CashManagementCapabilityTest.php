<?php

namespace Tests\Feature;

use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashManagementCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_management_capability_exists(): void
    {
        $this->artisan(
            'imp:install-capabilities'
        );

        $capability = CapabilityDefinition::where(
            'name',
            'CashManagement'
        )->first();

        $this->assertNotNull(
            $capability
        );

        $this->assertSame(
            'CFOImp',
            $capability->owner
        );

        $this->assertSame(
            'Finance',
            $capability->area
        );
    }

    public function test_cash_management_has_executive_actions(): void
    {
        $this->artisan(
            'imp:install-capabilities'
        );

        $capability = CapabilityDefinition::where(
            'name',
            'CashManagement'
        )
            ->with('actions')
            ->first();

        $this->assertNotNull(
            $capability
        );

        $this->assertCount(
            3,
            $capability->actions
        );
    }
}
