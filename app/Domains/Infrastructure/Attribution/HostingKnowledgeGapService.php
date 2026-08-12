<?php

namespace App\Domains\Infrastructure\Attribution;

use App\Models\AttributionCandidate;
use App\Models\ManagedService;
use Illuminate\Support\Collection;

class HostingKnowledgeGapService
{
    public function gaps(): Collection
    {
        return AttributionCandidate::query()
            ->where(
                'subject_type',
                'client'
            )
            ->where(
                'relationship_type',
                'hosted_on'
            )
            ->whereNull(
                'target_id'
            )
            ->where(
                'status',
                'candidate'
            )
            ->get()
            ->map(
                fn (AttributionCandidate $candidate) => $this->gap(
                    $candidate
                )
            )
            ->sortByDesc(
                'score'
            )
            ->values();
    }

    private function gap(
        AttributionCandidate $candidate
    ): array {
        $evidence =
            collect(
                $candidate->evidence
                ?? []
            );

        $monthlyRates =
            $evidence
                ->map(
                    fn (array $item) => (float) (
                        $item['metadata'][
                            'monthly_rate'
                        ]
                        ?? 0
                    )
                )
                ->filter(
                    fn (float $value) => $value > 0
                );

        /*
         * Multiple hosting lines for the same client may represent
         * separate hosted properties/services. We want the latest
         * month's commercial total, not the sum of every historical
         * invoice line ever seen.
         */
        $latestInvoiceDate =
            $evidence
                ->map(
                    fn (array $item) => $item['metadata'][
                            'invoice_date'
                        ]
                        ?? null
                )
                ->filter()
                ->sortDesc()
                ->first();

        $latestEvidence =
            $latestInvoiceDate
                ? $evidence->filter(
                    fn (array $item) => (
                        $item['metadata'][
                            'invoice_date'
                        ]
                        ?? null
                    )
                        === $latestInvoiceDate
                )
                : collect();

        $monthlyRevenue =
            round(
                (float)
                $latestEvidence
                    ->sum(
                        fn (array $item) => (float) (
                            $item['metadata'][
                                'monthly_rate'
                            ]
                            ?? 0
                        )
                    ),
                2
            );

        if (
            $monthlyRevenue <= 0
            && $monthlyRates->isNotEmpty()
        ) {
            $monthlyRevenue =
                round(
                    (float)
                    $monthlyRates->max(),
                    2
                );
        }

        $managedServiceExists =
            ManagedService::query()
                ->where(
                    'client_id',
                    $candidate->subject_id
                )
                ->where(
                    'service_type',
                    'managed_hosting'
                )
                ->where(
                    'status',
                    'active'
                )
                ->exists();

        $evidenceCount =
            $evidence->count();

        $score =
            min(
                $monthlyRevenue,
                200
            )
            + min(
                $evidenceCount * 2,
                50
            )
            + $candidate->confidence
            + (
                $managedServiceExists
                    ? 25
                    : 0
            );

        return [
            'candidate' => $candidate,

            'client_id' => $candidate->subject_id,

            'monthly_revenue' => $monthlyRevenue,

            'evidence_count' => $evidenceCount,

            'confidence' => $candidate->confidence,

            'managed_service_exists' => $managedServiceExists,

            'score' => round(
                $score,
                2
            ),

            'question' => 'Which server hosts this client?',
        ];
    }
}
