<?php

namespace App\Domains\BusinessBrain\Learning;

class LearningConfidence
{
    public function __construct(
        public int $sampleSize,

        public bool $usable,

        public int $confidence
    ) {}
}
