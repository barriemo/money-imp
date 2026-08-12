<?php

namespace App\Domains\BusinessBrain;

use Illuminate\Support\Collection;

class BusinessBrain
{
    public function __construct(
        public Collection $observations,
        public Collection $insights,
        public Collection $questions,
        public Collection $recommendations
    ) {}

    public static function empty(): self
    {
        return new self(
            observations: collect(),
            insights: collect(),
            questions: collect(),
            recommendations: collect()
        );
    }
}
