<?php

namespace App\Domains\BusinessClassification\Services;

use App\Domains\BusinessClassification\DTOs\BusinessClassificationCandidate;
use App\Domains\BusinessClassification\Enums\BusinessDestinationCategory;
use App\Domains\BusinessClassification\Enums\ClassificationRole;
use Domains\Businesses\Models\Business;
use Illuminate\Support\Collection;

class BusinessClassificationCandidateService
{
    public function suggest(
        Business $business
    ): Collection {
        $name =
            strtolower(
                $business->name
            );

        $candidates = collect();

        if (
            str_contains($name, 'hotel')
        ) {
            $candidates->push(
                new BusinessClassificationCandidate(
                    BusinessDestinationCategory::Accommodation,
                    ClassificationRole::Primary
                )
            );
        }

        if (
            str_contains($name, 'spa')
        ) {
            $candidates->push(
                new BusinessClassificationCandidate(
                    BusinessDestinationCategory::Wellness,
                    ClassificationRole::Secondary
                )
            );
        }

        return $candidates;
    }
}
