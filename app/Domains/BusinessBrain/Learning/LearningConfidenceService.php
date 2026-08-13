<?php

namespace App\Domains\BusinessBrain\Learning;

class LearningConfidenceService
{
    public function forSample(
        int $sampleSize
    ): LearningConfidence {
        return new LearningConfidence(
            sampleSize: $sampleSize,

            usable: $sampleSize >= 5,

            confidence: match (true) {
                $sampleSize >= 30 => 100,
                $sampleSize >= 20 => 90,
                $sampleSize >= 10 => 75,
                $sampleSize >= 5 => 60,
                default => 0,
            }
        );
    }
}
