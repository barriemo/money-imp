<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityEvidenceService;
use App\Models\CapabilityDefinition;
use Tests\TestCase;

class CapabilityEvidenceTest extends TestCase
{
    public function test_capability_evidence_detects_existing_layers(): void
    {
        $capability = new CapabilityDefinition([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
        ]);

        $evidence = app(CapabilityEvidenceService::class)->inspect(
            $capability
        );

        $this->assertTrue(
            $evidence['model']
        );

        $this->assertTrue(
            $evidence['service']
        );

        $this->assertTrue(
            $evidence['presenter']
        );
    }

    public function test_capability_evidence_returns_false_for_missing_capability(): void
    {
        $capability = new CapabilityDefinition([
            'name' => 'DoesNotExist',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
        ]);

        $evidence = app(CapabilityEvidenceService::class)->inspect(
            $capability
        );

        $this->assertFalse(
            $evidence['model']
        );

        $this->assertFalse(
            $evidence['service']
        );
    }
}
