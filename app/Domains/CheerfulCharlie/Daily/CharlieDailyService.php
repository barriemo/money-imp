<?php

namespace App\Domains\CheerfulCharlie\Daily;

use App\Domains\CheerfulCharlie\Review\CharlieReviewEngine;
use App\Models\CharlieDailyBrief;
use App\Models\CharlieReview;
use App\Models\Client;

class CharlieDailyService
{
    public function __construct(
        private CharlieReviewEngine $reviews,
        private CharlieReviewDeltaService $deltas,
        private CharlieDailyPriorityService $priorities
    ) {}

    public function build(): CharlieDailyBrief
    {
        $clients = Client::query()
            ->where(
                'status',
                'active'
            )
            ->get();

        $allFindings = collect();

        $newCount = 0;
        $resolvedCount = 0;

        foreach ($clients as $client) {
            $previous = CharlieReview::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->latest(
                    'reviewed_at'
                )
                ->first();

            $current =
                $this->reviews
                    ->review(
                        $client
                    );

            if ($previous) {
                $delta =
                    $this->deltas
                        ->compare(
                            $previous,
                            $current
                        );

                $newCount +=
                    $delta[
                        'new_count'
                    ];

                $resolvedCount +=
                    $delta[
                        'resolved_count'
                    ];
            } else {
                $newCount +=
                    $current
                        ->findings
                        ->count();
            }

            foreach (
                $current->findings as $finding
            ) {
                $allFindings->push(
                    $finding
                );
            }
        }

        $top =
            $this->priorities
                ->top(
                    $allFindings
                );

        $attentionCount =
            $allFindings
                ->whereIn(
                    'severity',
                    [
                        'critical',
                        'high',
                    ]
                )
                ->count();

        $monthlyValue =
            (float) $allFindings
                ->sum(
                    'estimated_monthly_value'
                );

        return CharlieDailyBrief::updateOrCreate(
            [
                'brief_date' => today(),
            ],
            [
                'client_count' => $clients->count(),

                'attention_count' => $attentionCount,

                'new_finding_count' => $newCount,

                'resolved_finding_count' => $resolvedCount,

                'estimated_monthly_value' => $monthlyValue,

                'summary' => [
                    'top_findings' => $top
                        ->map(
                            fn ($finding) => [
                                'client_id' => $finding
                                    ->client_id,

                                'title' => $finding
                                    ->title,

                                'category' => $finding
                                    ->category,

                                'severity' => $finding
                                    ->severity,

                                'priority_score' => $finding
                                    ->priority_score,
                            ]
                        )
                        ->values()
                        ->all(),
                ],

                'metadata' => [
                    'generated_at' => now()
                        ->toIso8601String(),
                ],
            ]
        );
    }
}
