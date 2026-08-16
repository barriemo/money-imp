<?php

namespace App\Domains\BusinessBrain\Cfo\Conversation\Intent;

class CfoIntentResolver
{
    public function resolve(
        string $question
    ): ?string {
        $question =
            strtolower(
                trim(
                    $question
                )
            );

        if (
            str_contains(
                $question,
                'uncertain'
            )
            || (
                str_contains(
                    $question,
                    'why'
                )
                && str_contains(
                    $question,
                    'confidence'
                )
            )
        ) {
            return 'explain_uncertainty';
        }

        if (
            str_contains(
                $question,
                'cash'
            )
            || str_contains(
                $question,
                'safe'
            )
            || str_contains(
                $question,
                'available'
            )
        ) {
            return 'cash_position';
        }

        if (
            str_contains(
                $question,
                'financial risk'
            )
            || str_contains(
                $question,
                'business risk'
            )
            || str_contains(
                $question,
                'cash risk'
            )
            || str_contains(
                $question,
                'biggest financial'
            )
        ) {
            return 'biggest_risk';
        }

        if (
            str_contains(
                $question,
                'financial priority'
            )
            || str_contains(
                $question,
                'cfo priority'
            )
            || (
                str_contains(
                    $question,
                    'focus'
                )
                && str_contains(
                    $question,
                    'financial'
                )
            )
        ) {
            return 'today_priority';
        }

        return null;
    }
}
