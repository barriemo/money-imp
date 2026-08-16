<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Conversation\Intent;

class ClientLedgerIntentResolver
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
                'evidence is still missing'
            )
            || str_contains(
                $question,
                'what evidence is missing'
            )
            || str_contains(
                $question,
                'missing evidence'
            )
        ) {
            return 'show_missing_evidence';
        }

        if (
            str_contains(
                $question,
                'supports my assertion'
            )
            || str_contains(
                $question,
                'supports what i said'
            )
            || str_contains(
                $question,
                'supporting evidence'
            )
        ) {
            return 'show_supporting_evidence';
        }

        if (
            str_contains(
                $question,
                'contradicts my assertion'
            )
            || str_contains(
                $question,
                'contradicts what i said'
            )
            || str_contains(
                $question,
                'contradicting evidence'
            )
        ) {
            return 'show_contradicting_evidence';
        }

        if (
            str_contains(
                $question,
                'test that'
            )
            || str_contains(
                $question,
                'verify that'
            )
            || str_contains(
                $question,
                'test the assertion'
            )
        ) {
            return 'verify_assertion';
        }

        if (
            str_contains(
                $question,
                'know what happened'
            )
            || str_contains(
                $question,
                'i know'
            )
        ) {
            return 'begin_user_assertion';
        }

        if (
            str_contains(
                $question,
                'invoice'
            )
        ) {
            return 'show_invoices';
        }

        if (
            str_contains(
                $question,
                'bank'
            )
            || str_contains(
                $question,
                'receipt'
            )
            || str_contains(
                $question,
                'payment'
            )
        ) {
            return 'show_bank_receipts';
        }

        if (
            str_contains(
                $question,
                'why'
            )
        ) {
            return 'explain_anomaly';
        }

        if (
            str_contains(
                $question,
                'know what happened'
            )
            || str_contains(
                $question,
                'i know'
            )
        ) {
            return 'user_assertion';
        }

        return 'summary';
    }
}
