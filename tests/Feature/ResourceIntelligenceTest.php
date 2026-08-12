<?php

namespace Tests\Feature;

use App\Domains\ResourceIntelligence\Resource;
use App\Domains\ResourceIntelligence\ResourceAllocation;
use Tests\TestCase;

class ResourceIntelligenceTest extends TestCase
{
    public function test_resource_can_represent_a_specialist_with_skills(): void
    {
        $resource = new Resource(
            name: 'John Smith',
            type: 'freelancer',
            skills: [
                'Laravel',
                'API Integration',
            ],
            costRate: 65
        );

        $this->assertSame(
            'John Smith',
            $resource->name
        );

        $this->assertContains(
            'Laravel',
            $resource->skills
        );

        $this->assertSame(
            65.0,
            $resource->costRate
        );
    }

    public function test_resource_can_be_allocated_to_work(): void
    {
        $allocation = new ResourceAllocation(
            resource: 'John Smith',
            project: 'Walker CRM',
            expectedHours: 40
        );

        $this->assertSame(
            'Walker CRM',
            $allocation->project
        );

        $this->assertSame(
            40,
            $allocation->expectedHours
        );
    }
}
