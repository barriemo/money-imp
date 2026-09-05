<?php

namespace App\Domains\Payment\Decision;

class PaymentDecisionPresenter
{
    public function present(
        PaymentDecision $decision
    ): string {
        $lines = [
            'MONEY IMP',
            'Payment OS Decision',
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
            'Scope: Payment decision guidance only. This surface does not allocate or approve payments, prove that a payment did or did not occur beyond the bounded evidence conclusion, rank clients, initiate collections or chasing, draft or send invoices, execute payment workflows, mutate accounting or commercial truth, or persist decision outcomes. Human review remains final.';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
