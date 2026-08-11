<?php

namespace App\Domains\CheerfulCharlie\Priority;

use App\Domains\BusinessMemory\Enums\BusinessMemoryInsightType;
use App\Domains\CheerfulCharlie\DTOs\CharliePriority;
use App\Models\BusinessMemoryInsight;
use Illuminate\Support\Collection;

class CharliePriorityEngine
{
    public function rank(
        Collection $insights
    ): Collection {
        return $insights
            ->map(
                fn (BusinessMemoryInsight $insight) => $this->score($insight)
            )
            ->sortByDesc(
                fn (CharliePriority $priority) => $priority->score
            )
            ->values();
    }

    public function score(
        BusinessMemoryInsight $insight
    ): CharliePriority {
        $financialImpact =
            $this->financialImpact(
                $insight
            );

        $confidence =
            max(
                0,
                min(
                    100,
                    $insight->confidence
                )
            );

        $urgency =
            $this->urgency(
                $insight
            );

        $risk =
            $this->risk(
                $insight
            );

        $ease =
            $this->ease(
                $insight
            );

        $score = round(
            ($financialImpact * 0.30)
            + ($confidence * 0.20)
            + ($urgency * 0.20)
            + ($risk * 0.20)
            + ($ease * 0.10),
            2
        );

        return new CharliePriority(
            insight: $insight,
            score: $score,
            financialImpactScore: $financialImpact,
            confidenceScore: $confidence,
            urgencyScore: $urgency,
            riskScore: $risk,
            easeScore: $ease,
            reasons: $this->reasons(
                $insight,
                $financialImpact,
                $confidence,
                $urgency,
                $risk,
                $ease
            ),
        );
    }

    private function financialImpact(
        BusinessMemoryInsight $insight
    ): float {
        $amount = (float) (
            $insight->metadata[
                'monthly_financial_impact'
            ]
            ?? 0
        );

        if ($amount <= 0) {
            return match (
                $insight->insight_type
            ) {
                BusinessMemoryInsightType::Opportunity => 60,

                BusinessMemoryInsightType::Risk => 50,

                BusinessMemoryInsightType::FollowUp => 40,

                default => 20,
            };
        }

        return match (true) {
            $amount >= 1000 => 100,

            $amount >= 500 => 90,

            $amount >= 250 => 80,

            $amount >= 100 => 70,

            $amount >= 50 => 60,

            default => 40,
        };
    }

    private function urgency(
        BusinessMemoryInsight $insight
    ): float {
        return match (
            $insight->insight_type
        ) {
            BusinessMemoryInsightType::FollowUp => 95,

            BusinessMemoryInsightType::Risk => 85,

            BusinessMemoryInsightType::Question => 60,

            BusinessMemoryInsightType::Opportunity => 55,

            default => 50,
        };
    }

    private function risk(
        BusinessMemoryInsight $insight
    ): float {
        return match (
            $insight->insight_type
        ) {
            BusinessMemoryInsightType::Risk => 100,

            BusinessMemoryInsightType::FollowUp => 70,

            BusinessMemoryInsightType::Opportunity => 35,

            BusinessMemoryInsightType::Question => 30,

            default => 40,
        };
    }

    private function ease(
        BusinessMemoryInsight $insight
    ): float {
        return (float) (
            $insight->metadata[
                'ease_score'
            ]
            ?? match (
                $insight->insight_type
            ) {
                BusinessMemoryInsightType::FollowUp => 90,

                BusinessMemoryInsightType::Question => 80,

                BusinessMemoryInsightType::Opportunity => 60,

                BusinessMemoryInsightType::Risk => 50,

                default => 50,
            }
        );
    }

    private function reasons(
        BusinessMemoryInsight $insight,
        float $financialImpact,
        float $confidence,
        float $urgency,
        float $risk,
        float $ease
    ): array {
        return [
            'financial_impact' => $financialImpact,

            'confidence' => $confidence,

            'urgency' => $urgency,

            'risk' => $risk,

            'ease' => $ease,

            'type' => $insight
                ->insight_type
                ->value,
        ];
    }
}
