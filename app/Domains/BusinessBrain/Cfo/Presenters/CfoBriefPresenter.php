<?php

namespace App\Domains\BusinessBrain\Cfo\Presenters;

use App\Domains\BusinessBrain\Cfo\Briefing\CfoBrief;

class CfoBriefPresenter
{
    public function present(
        CfoBrief $brief
    ): string {
        $position =
            $brief->financialPosition;

        $lines = [
            'MONEY IMP',
            'Chief Financial Officer',
            '',
            'Overall position:',
            strtoupper(
                $brief->overallStatus
            ),
            '',
            sprintf(
                'Confidence: %d%%',
                $brief->overallConfidence
            ),
            '',
            'Financial position:',
            sprintf(
                '- Verified cash: £%s',
                number_format(
                    $position->cash->verifiedCash,
                    2
                )
            ),
            sprintf(
                '- Ledger receivables: £%s',
                number_format(
                    $position->receivables->ledgerOutstanding,
                    2
                )
            ),
            sprintf(
                '- Known liabilities: £%s',
                number_format(
                    $position->liabilities->known,
                    2
                )
            ),
            sprintf(
                '- Verified credit exposure: £%s',
                number_format(
                    $position->credit->verifiedExposure,
                    2
                )
            ),
            '',
            'Business Brain:',
            sprintf(
                '- Active investigations: %d',
                $brief->businessBrain->activeInvestigationCount
            ),
            sprintf(
                '- Waiting investigations: %d',
                $brief->businessBrain->waitingInvestigationCount
            ),
            sprintf(
                '- Investigation candidates: %d',
                $brief->businessBrain->candidateCount
            ),
        ];

        $this->appendSection(
            $lines,
            'Strengths',
            $brief->strengths
        );

        $this->appendSection(
            $lines,
            'Risks',
            $brief->risks
        );

        $this->appendSection(
            $lines,
            'Critical unknowns',
            $brief->unknowns
        );

        if ($brief->bestNextVerification) {
            $lines[] = '';
            $lines[] = 'Best next evidence action:';
            $lines[] =
                sprintf(
                    '- %s (£%s)',
                    $brief->bestNextVerification->subject,
                    number_format(
                        $brief->bestNextVerification->amount
                            ?? 0,
                        2
                    )
                );

            $lines[] =
                '- '
                .$brief->bestNextVerification
                    ->recommendedAction;
        }

        $this->appendSection(
            $lines,
            "Today's priorities",
            $brief->priorities
        );

        $this->appendSection(
            $lines,
            'Recommended actions',
            $brief->recommendations
        );

        $this->appendSection(
            $lines,
            'Questions worth answering',
            $brief->questions
        );

        return implode(
            PHP_EOL,
            $lines
        );
    }

    private function appendSection(
        array &$lines,
        string $title,
        array $items
    ): void {
        if ($items === []) {
            return;
        }

        $lines[] = '';
        $lines[] = $title.':';

        foreach ($items as $item) {
            $lines[] =
                '- '.$item;
        }
    }
}
