<?php

namespace App\Domains\Executive;

class ExecutiveDashboard
{
    public function __construct(
        private ExecutiveHealthReasoner $health
    ) {}

    public function build(): array
    {
        $viability =
            $this->health
                ->answer(
                    ExecutiveQuestion::canKeepLightsOn()
                );

        return [
            'viability' => $viability,
        ];
    }
}
