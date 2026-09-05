<?php

namespace App\Domains\Billing\Decision;

class BillingDecisionPresenter
{
    public function present(
        BillingDecision $decision
    ): string {
        $lines = [
            'MONEY IMP',
            'Billing OS Decision',
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
                        $evidence->key,
                        $evidence->label
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
                        '  - %s %s — %s',
                        strtoupper(
                            $constraint->type
                        ),
                        $constraint->key,
                        $constraint->label
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
            'Scope: Billing decision guidance only. This surface describes what the canonical observed-billing evidence supports saying for one exact client service. It does not establish contractual billing obligation, determine what should be billed, draft or send invoices, perform bulk billing, write invoices to FreeAgent, rank clients or services, execute billing workflows, mutate accounting or commercial truth, or persist decision outcomes. Human review remains final.';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
