<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Attribution\ResourceContributionRepository;
use App\Domains\ResourceIntelligence\Attribution\ResourceWorkAttribution;
use App\Domains\ResourceIntelligence\Graph\ResourceContributionGraphProvider;
use Tests\TestCase;

class ResourceContributionGraphProviderTest extends TestCase
{
    public function test_resource_contribution_enters_client_graph(): void
    {
        $repository =
            new ResourceContributionRepository;

        $repository->add(
            new ResourceWorkAttribution(
                resource: 'John Smith',

                workLogId: 'work-1',

                hours: 20,

                cost: 1300,

                valueCreated: 3800
            )
        );

        $provider =
            new ResourceContributionGraphProvider(
                $repository
            );

        $graph =
            $provider->build(
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

        $this->assertSame(
            2500.0,
            $graph->nodes
                ->last()
                ->attributes['margin']
        );
    }
}
