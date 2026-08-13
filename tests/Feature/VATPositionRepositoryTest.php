<?php

namespace Tests\Feature;

use App\Domains\VATIntelligence\VATPosition;
use App\Domains\VATIntelligence\VATPositionRepository;
use Tests\TestCase;

class VATPositionRepositoryTest extends TestCase
{
    public function test_vat_position_can_be_retrieved_for_client(): void
    {
        $repository =
            app(
                VATPositionRepository::class
            );

        $repository->add(
            'client-1',
            new VATPosition(
                vatCollected: 50000,

                vatPaid: 20000,

                dueDate: now()
            )
        );

        $position =
            $repository->findForClient(
                'client-1'
            );

        $this->assertNotNull(
            $position
        );

        $this->assertSame(
            30000.0,
            $position->liability()
        );
    }
}
