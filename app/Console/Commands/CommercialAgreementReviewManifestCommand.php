<?php

namespace App\Console\Commands;

use App\Domains\CommercialTruth\Services\CommercialAgreementReviewManifestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CommercialAgreementReviewManifestCommand extends Command
{
    protected $signature =
        'money:contract-review-manifest
        {--as-of= : Review date YYYY-MM-DD}
        {--json : Emit machine-readable JSON}';

    protected $description =
        'Display routine contract candidates for human review without writing contractual truth';

    public function handle(
        CommercialAgreementReviewManifestService $manifest
    ): int {
        try {
            $asOf =
                $this->asOf();

            $items =
                $manifest->routine(
                    $asOf
                );

            if (
                $this->option(
                    'json'
                )
            ) {
                $this->line(
                    json_encode(
                        [
                            'as_of' => $asOf->toDateString(),

                            'warning' => 'Observed billing is not contractual truth. This manifest requires explicit human review.',

                            'candidate_count' => $items->count(),

                            'supported_current_monthly_equivalent' => round(
                                (float) $items->sum(
                                    'supported_current_monthly_equivalent'
                                ),
                                2
                            ),

                            'candidates' => $items->all(),
                        ],
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                );

                return self::SUCCESS;
            }

            $this->info(
                'Contract Review Manifest'
            );

            $this->line(
                'As of: '
                .$asOf->toDateString()
            );

            $this->warn(
                'OBSERVED BILLING IS NOT CONTRACT TRUTH'
            );

            $this->line(
                sprintf(
                    '%d routine candidates; supported current monthly-equivalent billing £%s.',
                    $items->count(),
                    number_format(
                        (float) $items->sum(
                            'supported_current_monthly_equivalent'
                        ),
                        2
                    )
                )
            );

            $this->newLine();

            foreach (
                $items as $index => $item
            ) {
                $this->line(
                    sprintf(
                        '#%d %s — %s',
                        $index + 1,
                        $item['client'],
                        $item['service']
                    )
                );

                $this->line(
                    sprintf(
                        '  Proposed review: %s | monthly | £%s',
                        $item['proposed_action'],
                        number_format(
                            $item[
                                'supported_current_monthly_equivalent'
                            ],
                            2
                        )
                    )
                );

                $this->line(
                    sprintf(
                        '  Evidence: %d observations | %s → %s',
                        $item[
                            'observed_evidence_count'
                        ],
                        $item[
                            'first_observed_on'
                        ]
                            ?? 'NONE',
                        $item[
                            'last_observed_on'
                        ]
                            ?? 'NONE'
                    )
                );

                $this->line(
                    '  ClientService: '
                    .$item['client_service_id']
                );

                $this->line(
                    '  '
                    .$item['warning']
                );

                $this->newLine();
            }

            $this->warn(
                'NO CONTRACTUAL OR COVERAGE TRUTH WAS WRITTEN.'
            );

            return self::SUCCESS;
        } catch (
            Throwable $exception
        ) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function asOf(): CarbonImmutable
    {
        $value =
            trim(
                (string) (
                    $this->option(
                        'as-of'
                    )
                    ?? ''
                )
            );

        if (
            $value === ''
        ) {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                $value
            )->startOfDay();
        } catch (
            Throwable
        ) {
            throw new \InvalidArgumentException(
                '--as-of must be YYYY-MM-DD.'
            );
        }
    }
}
