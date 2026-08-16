<?php

namespace App\Domains\BusinessBrain\Experience\Matching;

use App\Models\BusinessExperience;

class ExperienceMatch
{
    public function __construct(
        public BusinessExperience $experience,

        public int $score,

        public array $reasons = []
    ) {}
}
