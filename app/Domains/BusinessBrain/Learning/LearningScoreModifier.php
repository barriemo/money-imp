<?php

namespace App\Domains\BusinessBrain\Learning;

class LearningScoreModifier
{
    public function __construct(
        private ActionOutcomeProfileService $profiles,

        private LearningConfidenceService $confidence
    ) {}

    public function forType(
        string $type
    ): int {
        $profile =
            $this->profiles
                ->forType(
                    $type
                );

        if (! $profile) {
            return 0;
        }

        $learning =
            $this->confidence
                ->forSample(
                    $profile->completedCount
                );

        if (! $learning->usable) {
            return 0;
        }

        $successDelta =
            $profile->financialSuccessRate - 50;

        $rawModifier =
            ($successDelta / 50)
            * 10
            * ($learning->confidence / 100);

        return (int) round(
            max(
                -10,
                min(
                    10,
                    $rawModifier
                )
            )
        );
    }
}
