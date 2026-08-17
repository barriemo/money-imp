<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImpCapabilityCommandTest extends TestCase
{
    public function test_capability_generator_creates_model(): void
    {
        $path = app_path(
            'Models/TestGeneratedCapability.php'
        );

        if (File::exists($path)) {
            File::delete($path);
        }

        $this->artisan(
            'imp:capability TestGeneratedCapability --domain=Testing'
        )
            ->expectsOutputToContain(
                'Capability generated'
            )
            ->assertSuccessful();

        $this->assertTrue(
            File::exists($path)
        );

        File::delete($path);
    }
}
