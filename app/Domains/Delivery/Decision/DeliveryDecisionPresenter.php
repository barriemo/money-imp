<?php

namespace App\Domains\Delivery\Decision;

class DeliveryDecisionPresenter
{
    public function present(
        DeliveryDecision $decision
    ): string {
        $lines = [
            'MONEY IMP',
            'Delivery OS Decision',
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

        $lines[] =
            '';

        $lines[] =
            'Rationale:';

        $lines[] =
            '  '
            .$decision->rationale;

        $lines[] =
            '';

        $lines[] =
            'Evidence:';

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

        $lines[] =
            '';

        $lines[] =
            'Constraints:';

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

        $lines[] =
            '';

        $lines[] =
            'Missing truth:';

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

        $lines[] =
            '';

        $lines[] =
            'As of: '
            .$decision->asOf
                ->toIso8601String();

        $lines[] =
            '';

        $lines[] =
            'Scope: Delivery decision guidance only. This surface does not prioritise clients, perform human WorkLog review, change commercial disposition, decide recoverability or invoice readiness, assess delivery health, execute or persist actions.';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
