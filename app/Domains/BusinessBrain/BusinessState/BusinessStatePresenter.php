<?php

namespace App\Domains\BusinessBrain\BusinessState;

use Illuminate\Support\Collection;

class BusinessStatePresenter
{
    public function present(
        BusinessStateProjection $state
    ): string {
        $lines = [
            'MONEY IMP',
            'Business State',
            '',
            'As of: '.$state->asOf->toIso8601String(),
        ];

        $this->appendSection(
            lines: $lines,

            title: 'Financial facts',

            items: $state->financialFacts
        );

        $this->appendSection(
            lines: $lines,

            title: 'Commercial facts',

            items: $state->commercialFacts
        );

        $this->appendSection(
            lines: $lines,

            title: 'Work facts',

            items: $state->workFacts
        );

        $this->appendSection(
            lines: $lines,

            title: 'Known commercial conditions',

            items: $state->commercialConditions
        );

        $this->appendSection(
            lines: $lines,

            title: 'Unknown truth',

            items: $state->unknowns
        );

        $this->appendSection(
            lines: $lines,

            title: 'Evidence gaps',

            items: $state->evidenceGaps
        );

        $lines[] = '';
        $lines[] = 'Boundary:';
        $lines[] =
            '- This is a deterministic statement of current business truth.';
        $lines[] =
            '- It does not contain health scoring, priorities, recommendations or inferred actions.';

        return implode(
            PHP_EOL,
            $lines
        );
    }

    private function appendSection(
        array &$lines,
        string $title,
        Collection $items
    ): void {
        $lines[] = '';
        $lines[] = $title.':';

        if ($items->isEmpty()) {
            $lines[] = '- None.';

            return;
        }

        foreach ($items as $item) {
            $lines[] =
                '- '.$item;
        }
    }
}
