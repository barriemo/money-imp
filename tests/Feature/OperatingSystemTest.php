<?php

namespace Tests\Feature;

use App\Domains\OperatingSystem\Registries\CapabilityRegistry;
use App\Domains\OperatingSystem\Registries\SpecialistRegistry;
use App\Domains\OperatingSystem\Services\OperatingSystemService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OperatingSystemTest extends TestCase
{
    public function test_executive_team_is_registered(): void
    {
        $specialists =
            app(
                SpecialistRegistry::class
            )->all();

        $this->assertSame(
            12,
            $specialists->count()
        );

        $this->assertNotNull(
            $specialists->firstWhere(
                'key',
                'business_brain'
            )
        );

        $this->assertNotNull(
            $specialists->firstWhere(
                'key',
                'sales'
            )
        );

        $this->assertNotNull(
            $specialists->firstWhere(
                'key',
                'chief_of_staff'
            )
        );
    }

    public function test_capabilities_are_owned_by_specialists(): void
    {
        $capabilities =
            app(
                CapabilityRegistry::class
            )->all();

        $owners =
            app(
                SpecialistRegistry::class
            )
                ->all()
                ->pluck(
                    'key'
                );

        foreach ($capabilities as $capability) {
            $this->assertTrue(
                $owners->contains(
                    $capability->owner
                ),
                sprintf(
                    'Unknown specialist owner [%s] for capability [%s].',
                    $capability->owner,
                    $capability->key
                )
            );
        }
    }

    public function test_roadmap_advances_to_cfo_financial_position(): void
    {
        $service =
            app(
                OperatingSystemService::class
            );

        $this->assertSame(
            'Complete the unified CFO financial position and briefing layer.',
            $service->nextRecommendedWork()
        );
    }

    public function test_os_command_exposes_operating_system(): void
    {
        $exitCode =
            Artisan::call(
                'os'
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
            'Business Operating System',
            $output
        );

        $this->assertStringContainsString(
            'Business Brain',
            $output
        );

        $this->assertStringContainsString(
            'Sales Imp',
            $output
        );

        $this->assertStringContainsString(
            'Chief of Staff Imp',
            $output
        );

        $this->assertStringContainsString(
            'Next recommended work:',
            $output
        );
    }
}
