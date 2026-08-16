<?php

namespace App\Domains\FinancialTruth\Verification\Presenters;

use App\Domains\FinancialTruth\Verification\DTOs\VerificationCandidate;
use Illuminate\Support\Collection;

class VerificationQueuePresenter
{
    public function present(
        Collection $candidates
    ): string {
        $lines = [
            'MONEY IMP',
            'Financial Verification Queue',
            '',
            sprintf(
                '%d item%s require verification.',
                $candidates->count(),
                $candidates->count() === 1
                    ? ''
                    : 's'
            ),
        ];

        if ($candidates->isEmpty()) {
            $lines[] = '';
            $lines[] =
                'No unresolved financial verification candidates were found.';

            return implode(
                PHP_EOL,
                $lines
            );
        }

        foreach (
            $candidates as $index => $candidate
        ) {
            $lines[] = '';
            $lines[] =
                sprintf(
                    '%d. %s',
                    $index + 1,
                    $candidate->subject
                );

            $lines[] =
                sprintf(
                    '   Type: %s',
                    strtoupper(
                        str_replace(
                            '_',
                            ' ',
                            $candidate->type
                        )
                    )
                );

            if ($candidate->amount !== null) {
                $lines[] =
                    sprintf(
                        '   Reported amount: £%s',
                        number_format(
                            $candidate->amount,
                            2
                        )
                    );
            }

            $lines[] =
                sprintf(
                    '   Source: %s',
                    strtoupper(
                        $candidate->source
                    )
                );

            $lines[] =
                sprintf(
                    '   Evidence confidence: %d%%',
                    $candidate->confidence
                );

            $lines[] =
                sprintf(
                    '   Verification priority: %d/100',
                    $candidate->priority
                );

            $lines[] =
                '   Why: '.$candidate->reason;

            $lines[] =
                '   Next action: '
                .$candidate->recommendedAction;
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }

    public function presentCandidate(
        VerificationCandidate $candidate
    ): string {
        return sprintf(
            '%s (£%s): %s',
            $candidate->subject,
            number_format(
                $candidate->amount
                    ?? 0,
                2
            ),
            $candidate->recommendedAction
        );
    }
}
