<?php

namespace App\Domains\BusinessBrain;

use App\Domains\BusinessBrain\Contracts\BusinessObservationProvider;
use App\Domains\BusinessBrain\Providers\FinancialObservationProvider;
use Illuminate\Support\Collection;

class BusinessBrainService
{
    public function __construct(
        private FinancialObservationProvider $financial
    ) {}

    public function build(): BusinessBrain
    {
        $observations = collect();

        foreach ($this->providers() as $provider) {
            $observations =
                $observations->concat(
                    $provider->observations()
                );
        }

        $observations =
            $observations
                ->values();

        return new BusinessBrain(
            observations: $observations,

            insights: collect(),

            questions: $this->questionsFrom(
                $observations
            ),

            recommendations: collect()
        );
    }

    /**
     * @return array<int, BusinessObservationProvider>
     */
    private function providers(): array
    {
        return [
            $this->financial,
        ];
    }

    private function questionsFrom(
        Collection $observations
    ): Collection {
        $questions = collect();

        $cash =
            $observations->firstWhere(
                'type',
                'cash_position'
            );

        if (
            $cash
            && $cash->confidence < 100
        ) {
            $questions->push(
                new BusinessQuestion(
                    question: 'What are the current bank and card balances?',

                    reason: 'Charlie cannot yet establish a verified cash position.',

                    priority: 100,

                    context: [
                        'observation_type' => 'cash_position',

                        'confidence' => $cash->confidence,
                    ]
                )
            );
        }

        $liabilities =
            $observations->firstWhere(
                'type',
                'liabilities'
            );

        if (
            $liabilities
            && $liabilities->confidence < 100
        ) {
            $questions->push(
                new BusinessQuestion(
                    question: 'What liabilities are currently outstanding and which have been verified?',

                    reason: 'Charlie cannot yet calculate a trustworthy net cash position.',

                    priority: 90,

                    context: [
                        'observation_type' => 'liabilities',

                        'confidence' => $liabilities->confidence,
                    ]
                )
            );
        }

        return $questions
            ->sortByDesc(
                'priority'
            )
            ->values();
    }
}
