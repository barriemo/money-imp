<?php

namespace App\Domains\BusinessClassification\DTOs;

use App\Domains\BusinessClassification\Enums\BusinessDestinationCategory;
use App\Domains\BusinessClassification\Enums\ClassificationRole;

class BusinessClassificationCandidate
{
    public function __construct(
        public BusinessDestinationCategory $category,

        public ClassificationRole $role
    ) {}
}
