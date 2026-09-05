<?php

namespace App\Domains\Project\Decision;

class ProjectDecisionPresenter
{
    public function present(
        ProjectDecision $decision
    ): string {
        $lines = [
            'MONEY IMP',
            'Project OS Decision',
            '',
            'Question: '
                .$decision->question,
            'Status: '
                .strtoupper(
                    $decision->status
                ),
            'Recommendation confidence: '
                .$decision->confidence
                .'%',
            '',
            'Recommendation:',
        ];

        if ($decision->recommendation === null) {
            $lines[] =
                '  Deferred — no recommendation is established.';
        } else {
            $lines[] =
                '  '
                .$decision->recommendation;
        }

        $lines[] = '';
        $lines[] = 'Rationale:';
        $lines[] =
            '  '
            .$decision->rationale;

        $lines[] = '';
        $lines[] = 'Evidence:';

        if ($decision->evidence->isEmpty()) {
            $lines[] =
                '  - None.';
        } else {
            foreach (
                $decision->evidence as $evidence
            ) {
                $lines[] =
                    sprintf(
                        '  - %s [%d%%] %s — %s',
                        strtoupper(
                            $evidence->position
                        ),
                        $evidence->confidence,
                        $evidence->source,
                        $evidence->description
                    );
            }
        }

        $lines[] = '';
        $lines[] = 'Constraints:';

        if ($decision->constraints->isEmpty()) {
            $lines[] =
                '  - None.';
        } else {
            foreach (
                $decision->constraints as $constraint
            ) {
                $lines[] =
                    sprintf(
                        '  - %s [%d%%] %s — %s',
                        strtoupper(
                            $constraint->type
                        ),
                        $constraint->confidence,
                        $constraint->code,
                        $constraint->description
                    );
            }
        }

        $lines[] = '';
        $lines[] = 'Missing truth:';

        if ($decision->missingTruth->isEmpty()) {
            $lines[] =
                '  - None.';
        } else {
            foreach (
                $decision->missingTruth as $missing
            ) {
                $lines[] =
                    '  - '
                    .$missing;
            }
        }

        $lines[] = '';

        $lines[] =
            'As of: '
            .$decision->asOf
                ->toIso8601String();

        $lines[] = '';

        $lines[] =
            'Scope: Project decision guidance only. This surface does not classify project health, prioritise or rank projects, create or assign actions, execute project work, mutate project records or persist decision outcomes. Human review remains final.';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
