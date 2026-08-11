<?php

namespace App\Console\Commands;

use App\Domains\RevenueTruth\RevenueTruthService;
use Illuminate\Console\Command;

class ExecutiveTruthCommand extends Command
{
    protected $signature = 'money:executive-truth';

    protected $description =
        'Display the current executive truth for the business.';

    public function __construct(
        private RevenueTruthService $revenueTruth
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $revenue = $this->revenueTruth
            ->summary();

        $this->newLine();

        $this->line(
            str_repeat(
                '=',
                68
            )
        );

        $this->info(
            '                        EXECUTIVE TRUTH'
        );

        $this->line(
            str_repeat(
                '=',
                68
            )
        );

        $this->newLine();

        $this->info(
            'REVENUE TRUTH'
        );

        $this->table(
            [
                'Metric',
                'Value',
            ],
            [
                [
                    'Recommendations',
                    $revenue[
                        'recommendation_count'
                    ],
                ],
                [
                    'Clients affected',
                    $revenue[
                        'client_count'
                    ],
                ],
                [
                    'Missing recovery',
                    $revenue[
                        'missing_recovery_count'
                    ],
                ],
                [
                    'Under recovery',
                    $revenue[
                        'under_recovery_count'
                    ],
                ],
                [
                    'Recoverable monthly',
                    $this->money(
                        $revenue[
                            'recoverable_monthly'
                        ]
                    ),
                ],
                [
                    'Recoverable annual',
                    $this->money(
                        $revenue[
                            'recoverable_annual'
                        ]
                    ),
                ],
            ]
        );

        $highest =
            $revenue[
                'highest_priority'
            ];

        if ($highest) {
            $highest->loadMissing([
                'client',
                'supplierAsset.supplier',
                'evidence',
            ]);

            $this->newLine();

            $this->warn(
                'HIGHEST PRIORITY REVENUE RECOVERY'
            );

            $this->table(
                [
                    'Field',
                    'Value',
                ],
                [
                    [
                        'Client',
                        $highest
                            ->client
                            ?->name
                            ?? 'Unknown',
                    ],
                    [
                        'Recommendation',
                        $highest
                            ->title,
                    ],
                    [
                        'Asset',
                        $highest
                            ->supplierAsset
                            ?->name
                            ?? '-',
                    ],
                    [
                        'Supplier',
                        $highest
                            ->supplierAsset
                            ?->supplier
                            ?->supplier_name
                            ?? '-',
                    ],
                    [
                        'Monthly value',
                        $this->money(
                            $highest
                                ->estimated_monthly_value
                        ),
                    ],
                    [
                        'Annual value',
                        $this->money(
                            $highest
                                ->estimated_annual_value
                        ),
                    ],
                    [
                        'Confidence',
                        $highest
                            ->confidence
                            .'%',
                    ],
                    [
                        'Priority',
                        $highest
                            ->priority,
                    ],
                ]
            );

            if ($highest->description) {
                $this->newLine();

                $this->line(
                    $highest
                        ->description
                );
            }

            if (
                $highest
                    ->evidence
                    ->isNotEmpty()
            ) {
                $this->newLine();

                $this->line(
                    'Evidence:'
                );

                foreach (
                    $highest
                        ->evidence as $evidence
                ) {
                    $this->line(
                        '  - '
                        .$evidence
                            ->summary
                        .' ['
                        .$evidence
                            ->confidence
                        .'%]'
                    );
                }
            }
        }

        if (
            $revenue[
                'recommendation_count'
            ] > 0
        ) {
            $this->newLine();

            $this->info(
                'TOP REVENUE RECOMMENDATIONS'
            );

            $rows =
                $revenue[
                    'recommendations'
                ]
                    ->sortByDesc(
                        'priority'
                    )
                    ->take(10)
                    ->map(
                        function (
                            $item
                        ): array {
                            $item
                                ->loadMissing(
                                    'client'
                                );

                            return [
                                $item
                                    ->priority,

                                $item
                                    ->client
                                    ?->name
                                    ?? 'Unknown',

                                $item
                                    ->title,

                                $this->money(
                                    $item
                                        ->estimated_monthly_value
                                ),

                                $item
                                    ->confidence
                                    .'%',
                            ];
                        }
                    )
                    ->values()
                    ->all();

            $this->table(
                [
                    'Priority',
                    'Client',
                    'Recommendation',
                    'Monthly',
                    'Confidence',
                ],
                $rows
            );
        }

        $this->newLine();

        $this->line(
            str_repeat(
                '-',
                68
            )
        );

        $this->comment(
            'Revenue Truth is evidence-backed and recommendation-only. No billing action has been taken.'
        );

        $this->line(
            str_repeat(
                '-',
                68
            )
        );

        $this->newLine();

        return self::SUCCESS;
    }

    private function money(
        mixed $value
    ): string {
        return '£'
            .number_format(
                (float) $value,
                2
            );
    }
}
