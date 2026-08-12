<?php

namespace App\Domains\Reasoning;

use App\Domains\Reasoning\Contracts\ReasoningStrategy;
use App\Domains\Reasoning\Strategies\RevenueRecoveryReasoner;
use RuntimeException;

class Reasoner
{
    public function __construct(
        private RevenueRecoveryReasoner $revenueRecovery
    ) {}

    public function answer(
        array $graph,
        Question $question
    ): Answer {
        foreach (
            $this->strategies() as $strategy
        ) {
            if (
                ! $strategy->supports(
                    $question
                )
            ) {
                continue;
            }

            return $strategy->answer(
                $graph,
                $question
            );
        }

        throw new RuntimeException(
            'No reasoning strategy supports question type: '
            .$question->type
        );
    }

    /**
     * @return array<int, ReasoningStrategy>
     */
    private function strategies(): array
    {
        return [
            $this->revenueRecovery,
        ];
    }
}
