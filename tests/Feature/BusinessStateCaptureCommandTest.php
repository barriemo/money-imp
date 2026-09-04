<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaseline;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineCaptureService;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetric;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class BusinessStateCaptureCommandTest extends TestCase
{
    public function test_capture_command_explicitly_captures_current_baseline(): void
    {
        $baseline =
            new BusinessStateBaseline(
                metrics: collect([
                    new BusinessStateMetric(
                        domain: 'cash',

                        metric: 'safe_available_cash',

                        scope: 'business',

                        clientId: null,

                        client: null,

                        source: 'financial.cash.safeAvailableCash',

                        known: false,

                        value: null
                    ),
                ]),

                asOf: CarbonImmutable::parse(
                    '2026-09-04 12:00:00'
                )
            );

        $this->mock(
            BusinessStateBaselineCaptureService::class,
            function (
                MockInterface $mock
            ) use ($baseline): void {
                $mock
                    ->shouldReceive(
                        'capture'
                    )
                    ->once()
                    ->andReturn(
                        $baseline
                    );
            }
        );

        $exitCode =
            Artisan::call(
                'business:state:capture'
            );

        $this->assertSame(
            0,
            $exitCode
        );

        $output =
            Artisan::output();

        $this->assertStringContainsString(
            'Captured business-state baseline',
            $output
        );

        $this->assertStringContainsString(
            'with 1 metrics',
            $output
        );
    }
}
