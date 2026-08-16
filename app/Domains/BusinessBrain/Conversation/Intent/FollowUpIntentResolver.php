<?php

namespace App\Domains\BusinessBrain\Conversation\Intent;

class FollowUpIntentResolver
{
    public function resolve(
        string $question
    ): string {
        $question =
            strtolower(
                trim(
                    $question
                )
            );

        if (
            str_contains(
                $question,
                'why'
            )
            && (
                str_contains(
                    $question,
                    'confidence'
                )
                || str_contains(
                    $question,
                    '52%'
                )
            )
        ) {
            return 'explain_confidence';
        }

        if (
            str_contains(
                $question,
                'client-ledger'
            )
            || str_contains(
                $question,
                'client ledger'
            )
            || str_contains(
                $question,
                'ledger anomalies'
            )
        ) {
            return 'show_ledger_anomalies';
        }

        if (
            str_contains(
                $question,
                'evidence'
            )
            || str_contains(
                $question,
                'show me'
            )
        ) {
            return 'show_evidence';
        }

        if (
            str_contains(
                $question,
                'biggest'
            )
            || str_contains(
                $question,
                'main problem'
            )
            || str_contains(
                $question,
                'worst'
            )
        ) {
            return 'show_biggest_problem';
        }

        if (
            str_contains(
                $question,
                'sort'
            )
            || str_contains(
                $question,
                'resolve'
            )
            || str_contains(
                $question,
                'work through'
            )
        ) {
            return 'work_unresolved';
        }

        return 'summary';
    }
}
