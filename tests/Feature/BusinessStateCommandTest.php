<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessStateProjection;
use App\Domains\BusinessBrain\BusinessState\BusinessStateProjectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class BusinessStateCommandTest extends TestCase
{
    public function test_business_state_command_presents_current_truth_without_recommendations(): void
    {
        $projection =
            new BusinessStateProjection(
                financialFacts: collect([
                    'Verified cash evidence: £10,000.00.',
                    'Known liability exposure: £1,000.00.',
                ]),

                commercialFacts: collect([
                    'Active clients represented in current revenue truth: 2.',
                ]),

                workFacts: collect([
                    'Recorded unrecovered work value: £1,250.00.',
                ]),

                commercialConditions: collect([
                    'Example Client: Invoiced revenue remains unpaid — £3,000.00 remains outstanding.',
                ]),

                unknowns: collect([
                    'Safe available cash is unknown — Complete current evidence is not available.',
                ]),

                evidenceGaps: collect([
                    'Example Client: No attributable bank evidence — No canonical attributable bank transactions are linked to this active client.',
                ]),

                asOf: CarbonImmutable::parse(
                    '2026-09-04 12:00:00'
                )
            );

        $this->mock(
            BusinessStateProjectionService::class,
            function (
                MockInterface $mock
            ) use ($projection): void {
                $mock
                    ->shouldReceive(
                        'current'
                    )
                    ->once()
                    ->andReturn(
                        $projection
                    );
            }
        );

        $exitCode =
            Artisan::call(
                'business:state'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'MONEY IMP',
            $output
        );

        $this->assertStringContainsString(
            'Business State',
            $output
        );

        $this->assertStringContainsString(
            'Financial facts:',
            $output
        );

        $this->assertStringContainsString(
            'Known commercial conditions:',
            $output
        );

        $this->assertStringContainsString(
            'Unknown truth:',
            $output
        );

        $this->assertStringContainsString(
            'Evidence gaps:',
            $output
        );

        $this->assertStringContainsString(
            'Safe available cash is unknown',
            $output
        );

        $this->assertStringContainsString(
            'No attributable bank evidence',
            $output
        );

        $this->assertStringContainsString(
            'does not contain health scoring, priorities, recommendations or inferred actions',
            $output
        );

        $this->assertStringNotContainsString(
            'Recommended actions:',
            $output
        );

        $this->assertStringNotContainsString(
            "Today's priorities:",
            $output
        );
    }
}
