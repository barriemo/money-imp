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
                '- Reported bank balances: £%s',
                number_format(
                    $position->cash->reportedAccountingBalance,
                    2
                )
            ),
            sprintf(
                '- Reported card exposure: £%s',
                number_format(
                    $position->cash->reportedUnverifiedCardDebt,
                    2
                )
            ),
            sprintf(
                '- Safe available cash: %s',
                $position->cash->safeAvailableCash === null
                    ? 'Not established'
                    : '£'.number_format(
                        $position->cash->safeAvailableCash,
                        2
                    )
            ),
            sprintf(
                '- Bank evidence: %d connected accounts, %d verified',
                $position->cash->accountCount,
                $position->cash->verifiedAccountCount
            ),
            sprintf(
                '- Ledger receivables: £%s',
                number_format(
                    $position->receivables
                        ->ledgerOutstanding,
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
        ];

        if (
            $position->liabilities
                ->currentReportedExposure > 0
        ) {
            $lines[] =
                sprintf(
                    '- Reported current liability exposure: £%s',
                    number_format(
                        $position->liabilities
                            ->currentReportedExposure,
                        2
                    )
                );
        }

        if (
            $position->liabilities
                ->reportedOverdue > 0
        ) {
            $lines[] =
                sprintf(
                    '- Reported overdue, settlement unresolved: £%s',
                    number_format(
                        $position->liabilities
                            ->reportedOverdue,
                        2
                    )
                );
        }

        $reconciliation =
            $position->liabilities
                ->reconciliation;

        if ($reconciliation !== []) {

            $lines[] = '';

            $lines[] = 'Statutory reconciliation:';

            $lines[] =
                sprintf(
                    '- Reported obligations: £%s',
                    number_format(
                        (float) (
                            $reconciliation['reported_total'] ?? 0
                        ),
                        2
                    )
                );

            $lines[] =
                sprintf(
                    '- Payments observed: £%s',
                    number_format(
                        (float) (
                            $reconciliation['payments_observed'] ?? 0
                        ),
                        2
                    )
                );

            $lines[] =
                sprintf(
                    '- Unresolved difference: £%s',
                    number_format(
                        (float) (
                            $reconciliation['unresolved_difference'] ?? 0
                        ),
                        2
                    )
                );

            $lines[] =
                sprintf(
                    '- Confidence: %s',
                    ucfirst(
                        (string) (
                            $reconciliation['confidence'] ?? 'unknown'
                        )
                    )
                );
        }

        if (
            $position->liabilities
                ->reportedUpcoming > 0
        ) {
            $lines[] =
                sprintf(
                    '- Reported upcoming liabilities: £%s',
                    number_format(
                        $position->liabilities
                            ->reportedUpcoming,
                        2
                    )
                );
        }

        foreach (
            $position->liabilities->reportedItems as $item
        ) {
            $label = match (
                $item['type'] ?? null
            ) {
                'vat' => 'VAT',
                'paye' => 'PAYE',
                'corporation_tax' => 'Corporation tax',
                default => ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        (string) (
                            $item['type']
                            ?? 'liability'
                        )
                    )
                ),
            };

            $state = match (
                $item['assessment'] ?? null
            ) {
                'reported_overdue' => 'reported overdue',

                'reported_upcoming' => 'reported upcoming',

                default => 'reported',
            };

            $lines[] =
                sprintf(
                    '- %s %s: £%s due %s',
                    $label,
                    $state,
                    number_format(
                        (float) (
                            $item['amount']
                            ?? 0
                        ),
                        2
                    ),
                    $item['due_date']
                        ?? 'unknown'
                );
        }

        $lines[] =
            sprintf(
                '- Verified credit exposure: £%s',
                number_format(
                    $position->credit
                        ->verifiedExposure,
                    2
                )
            );

        $lines[] = '';
        if ($brief->commercialPosition !== null) {
            $commercial =
                $brief->commercialPosition;

            $lines[] =
                'Commercial evidence:';

            $lines[] =
                sprintf(
                    '- Observed current monthly-equivalent billing: £%s',
                    number_format(
                        $commercial
                            ->supportedCurrentMonthlyEquivalent,
                        2
                    )
                );

            $lines[] =
                sprintf(
                    '- Attached to canonical services: £%s across %d current recurring service(s)',
                    number_format(
                        $commercial
                            ->canonicalCurrentObservedMonthlyEquivalent,
                        2
                    ),
                    $commercial
                        ->canonicalCurrentRecurringServiceCount
                );

            $lines[] =
                sprintf(
                    '- Still unreconciled: £%s across %d current recurring candidate(s)',
                    number_format(
                        $commercial
                            ->unreconciledCurrentMonthlyEquivalent,
                        2
                    ),
                    $commercial
                        ->unreconciledCurrentRecurringCandidateCount
                );

            $lines[] =
                sprintf(
                    '- Active canonical services: %d',
                    $commercial
                        ->canonicalActiveServiceCount
                );

            $lines[] =
                sprintf(
                    '- Service candidates ready for existence review: %d',
                    $commercial
                        ->readyForReviewCount
                );

            $lines[] =
                sprintf(
                    '- Invoice attribution candidates ready for review: %d',
                    $commercial
                        ->attributionReviewReadyCount
                );

            $lines[] =
                sprintf(
                    '- Recently observed recurring billing excluded from current: £%s',
                    number_format(
                        $commercial
                            ->recentlyObservedMonthlyEquivalent,
                        2
                    )
                );

            $contractStatus =
                match (
                    $commercial
                        ->contractedValueStatus
                ) {
                    'not_established' => 'billing terms not established',

                    'billing_rules_present_not_reconciled' => 'billing rules exist but are not reconciled',

                    default => str_replace(
                        '_',
                        ' ',
                        $commercial
                            ->contractedValueStatus
                    ),
                };

            $lines[] =
                sprintf(
                    '- Contracted recurring value: UNKNOWN (%s)',
                    $contractStatus
                );

            $lines[] =
                sprintf(
                    '- Evidence status: %s',
                    str_replace(
                        '_',
                        ' ',
                        $commercial
                            ->evidenceStatus
                    )
                );

            $lines[] =
                '- Evidence boundary: observed billing is not contracted MRR, cash, or margin.';
        }

        $lines[] = '';
        $lines[] = 'Business Brain:';

        $lines[] =
            sprintf(
                '- Active investigations: %d',
                $brief->businessBrain
                    ->activeInvestigationCount
            );

        $lines[] =
            sprintf(
                '- Waiting investigations: %d',
                $brief->businessBrain
                    ->waitingInvestigationCount
            );

        $lines[] =
            sprintf(
                '- Investigation candidates: %d',
                $brief->businessBrain
                    ->candidateCount
            );

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
            $lines[] =
                'Best next evidence action:';

            $lines[] =
                sprintf(
                    '- %s (£%s)',
                    $brief
                        ->bestNextVerification
                        ->subject,

                    number_format(
                        $brief
                            ->bestNextVerification
                            ->amount
                            ?? 0,
                        2
                    )
                );

            $lines[] =
                '- '
                .$brief
                    ->bestNextVerification
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
