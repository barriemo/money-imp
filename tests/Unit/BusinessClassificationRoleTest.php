<?php

namespace Tests\Unit;

use App\Domains\BusinessClassification\Enums\BusinessDestinationCategory;
use App\Domains\BusinessClassification\Enums\ClassificationRole;
use App\Domains\BusinessClassification\Services\BusinessClassificationCandidateService;
use Domains\Businesses\Models\Business;
use Tests\TestCase;

class BusinessClassificationRoleTest extends TestCase
{
    public function test_hotel_is_primary_and_spa_is_secondary(): void
    {
        $business = new Business([
            'name' => 'Apex City Quay Hotel & Spa',
        ]);

        $candidates = app(
            BusinessClassificationCandidateService::class
        )
            ->suggest($business);

        $this->assertSame(
            BusinessDestinationCategory::Accommodation,
            $candidates->first()->category,
        );

        $this->assertSame(
            ClassificationRole::Primary,
            $candidates->first()->role,
        );

        $spa = $candidates->first(
            fn ($candidate) =>
                $candidate->category === BusinessDestinationCategory::Wellness
        );

        $this->assertNotNull($spa);

        $this->assertSame(
            ClassificationRole::Secondary,
            $spa->role,
        );
    }
}
