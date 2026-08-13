<?php

namespace Tests\Feature;

use App\Domains\VATIntelligence\Providers\ClientVATExposureProvider;
use App\Domains\VATIntelligence\VATPosition;
use App\Domains\VATIntelligence\VATPositionRepository;
use Tests\TestCase;

class ClientVATExposureProviderTest extends TestCase
{
    public function test_client_vat_provider_returns_exposure(): void
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

        $exposure =
            app(
                ClientVATExposureProvider::class
            )->provide(
                'client-1'
            );

        $this->assertNotNull(
            $exposure
        );

        $this->assertSame(
            30000.0,
            $exposure->liability
        );
    }

    public function test_missing_vat_position_returns_no_exposure(): void
    {
        $exposure =
            app(
                ClientVATExposureProvider::class
            )->provide(
                'missing-client'
            );

        $this->assertNull(
            $exposure
        );
    }
}
