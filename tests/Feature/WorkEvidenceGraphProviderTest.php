<?php

namespace Tests\Feature;

use App\Domains\Evidence\EvidenceItem;
use App\Domains\Evidence\EvidenceRepository;
use App\Domains\WorkIntelligence\Graph\WorkEvidenceGraphProvider;
use Tests\TestCase;

class WorkEvidenceGraphProviderTest extends TestCase
{
    public function test_work_evidence_contributes_to_client_graph(): void
    {
        app(
            EvidenceRepository::class
        )->add(
            new EvidenceItem(
                type: 'work_log',
                source: 'staff',
                summary: 'Fixed Walker CRM integration',
                confidence: 90,
                metadata: [
                    'client_id' => 'client-1',
                    'work_log_id' => 'work-1',
                    'minutes' => 120,
                    'commercial_value' => 190,
                ]
            )
        );

        $graph =
            app(
                WorkEvidenceGraphProvider::class
            )
                ->build(
                    'client-1'
                );

        $this->assertCount(
            2,
            $graph->nodes
        );

        $this->assertCount(
            2,
            $graph->edges
        );
    }
}
