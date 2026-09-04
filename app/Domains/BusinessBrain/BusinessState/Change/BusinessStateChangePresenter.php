<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use App\Domains\BusinessBrain\Attention\Change\BusinessStateChangeAttention;

class BusinessStateChangePresenter
{
    private const MONEY_METRICS = [
        BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
        BusinessStateMetricCatalog::KNOWN_NET_POSITION,
        BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES,
        BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION,
        BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,
        BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
        BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,
        BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED,
        BusinessStateMetricCatalog::PAID_REVENUE_ACCORDING_TO_ACCOUNTING,
        BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
        BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE,
        BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE,
    ];

    public function present(
        BusinessStateChangeReport $report
    ): string {
        $lines = [
            'MONEY IMP',
            'Business Changes',
            '',
            'Current as of: '
                .$report->current
                    ->asOf
                    ->toIso8601String(),
        ];

        if (! $report->hasComparisonBaseline()) {
            $lines[] =
                'Compared with: No earlier captured business-state baseline.';

            $lines[] = '';
            $lines[] = 'Changes:';
            $lines[] =
                '- Change comparison is unavailable until an earlier baseline exists.';

            $lines[] = '';
            $lines[] = 'Attention:';
            $lines[] =
                '- None derived without a comparison baseline.';

            return $this->withBoundary(
                $lines
            );
        }

        $lines[] =
            'Compared with: '
            .$report->previous
                ->asOf
                ->toIso8601String();

        $lines[] = '';
        $lines[] = 'Changes:';

        if ($report->changes->isEmpty()) {
            $lines[] =
                '- No changes detected across the captured metric set.';
        } else {
            foreach ($report->changes as $change) {
                $lines[] =
                    '- '.$this->changeLine(
                        $change
                    );
            }
        }

        $lines[] = '';
        $lines[] = 'Attention:';

        if ($report->attention->isEmpty()) {
            $lines[] =
                '- None.';
        } else {
            foreach ($report->attention as $attention) {
                $lines[] =
                    '- '.$this->attentionType(
                        $attention
                    )
                    .' — '
                    .$attention->reason;
            }
        }

        return $this->withBoundary(
            $lines
        );
    }

    private function changeLine(
        BusinessStateChange $change
    ): string {
        $label =
            $this->label(
                $change->current->metric
            );

        return match ($change->kind) {
            BusinessStateChange::BECAME_KNOWN => $label
                .' became known at '
                .$this->value(
                    $change->current
                )
                .' (previously unknown).',

            BusinessStateChange::BECAME_UNKNOWN => $label
                .' became unknown; the previous established value was '
                .$this->value(
                    $change->previous
                )
                .'.',

            BusinessStateChange::INCREASED => $label
                .' increased from '
                .$this->value(
                    $change->previous
                )
                .' to '
                .$this->value(
                    $change->current
                )
                .'.',

            BusinessStateChange::DECREASED => $label
                .' decreased from '
                .$this->value(
                    $change->previous
                )
                .' to '
                .$this->value(
                    $change->current
                )
                .'.',

            default => $label
                .' changed.',
        };
    }

    private function value(
        BusinessStateMetric $metric
    ): string {
        if (! $metric->known) {
            return 'unknown';
        }

        if (
            in_array(
                $metric->metric,
                self::MONEY_METRICS,
                true
            )
        ) {
            return '£'
                .number_format(
                    (float) $metric->value,
                    2
                );
        }

        return number_format(
            (float) $metric->value,
            0
        );
    }

    private function label(
        string $metric
    ): string {
        return match ($metric) {
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH => 'Safe available cash',

            BusinessStateMetricCatalog::KNOWN_NET_POSITION => 'Known net position',

            BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES => 'Ledger outstanding receivables',

            BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION => 'Payments waiting allocation',

            BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES => 'Verified collectible receivables',

            BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE => 'Known liability exposure',

            BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE => 'Total liability exposure',

            BusinessStateMetricCatalog::CLIENT_RECORDS_MARKED_ACTIVE => 'Client records marked active',

            BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED => 'Gross invoiced revenue represented',

            BusinessStateMetricCatalog::PAID_REVENUE_ACCORDING_TO_ACCOUNTING => 'Paid revenue according to accounting',

            BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE => 'Outstanding invoiced revenue',

            BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE => 'Approved bank-backed payment evidence',

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE => 'Client records with outstanding revenue',

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE => 'Client records with weak payment evidence',

            BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE => 'Recorded unrecovered work value',

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE => 'Client records without work evidence',

            BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS => 'Verified bank account records',

            BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS => 'Unverified bank account records',

            BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS => 'Stale bank account records',

            default => str_replace(
                '_',
                ' ',
                ucfirst(
                    $metric
                )
            ),
        };
    }

    private function attentionType(
        BusinessStateChangeAttention $attention
    ): string {
        return match ($attention->type) {
            BusinessStateChangeAttention::TRUTH_LOST => 'Truth lost',

            BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED => 'Financial position reduced',

            BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED => 'Financial exposure increased',

            BusinessStateChangeAttention::COMMERCIAL_CONDITION_EXPANDED => 'Commercial condition expanded',

            BusinessStateChangeAttention::RECORDED_WORK_EXPOSURE_INCREASED => 'Recorded work exposure increased',

            BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED => 'Evidence coverage reduced',

            default => 'Attention',
        };
    }

    private function withBoundary(
        array $lines
    ): string {
        $lines[] = '';
        $lines[] = 'Boundary:';
        $lines[] =
            '- This is a deterministic comparison of captured and current business truth.';
        $lines[] =
            '- Attention is selected by explicit metric-and-direction rules without a cross-domain score.';
        $lines[] =
            '- Causal analysis and decision guidance are outside this report.';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
