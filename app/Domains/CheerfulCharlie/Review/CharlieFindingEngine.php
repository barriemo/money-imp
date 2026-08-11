<?php

namespace App\Domains\CheerfulCharlie\Review;

use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\CheerfulCharlie\Conflicts\CharlieConflictService;
use App\Domains\CheerfulCharlie\Curiosity\KnowledgeGapService;
use App\Models\BusinessMemoryInsight;
use App\Models\Client;
use Illuminate\Support\Collection;

class CharlieFindingEngine
{
    public function __construct(
        private CreateBusinessMemory $memories,
        private CharlieConflictService $conflicts,
        private KnowledgeGapService $gaps,
        private CharlieFindingPriority $priority
    ) {}

    public function findings(
        Client $client
    ): Collection {
        $memory = $this->memories
            ->execute($client);

        return collect()
            ->merge(
                $this->conflictFindings(
                    $client
                )
            )
            ->merge(
                $this->gapFindings(
                    $memory
                )
            )
            ->merge(
                $this->insightFindings(
                    $memory->id
                )
            )
            ->sortByDesc(
                'priority_score'
            )
            ->values();
    }

    private function conflictFindings(
        Client $client
    ): Collection {
        return $this->conflicts
            ->forSubject($client)
            ->map(
                function (array $conflict): array {
                    $confidence =
                        (int) $conflict[
                            'confidence'
                        ];

                    return [
                        'category' => 'contradiction',

                        'severity' => 'high',

                        'title' => 'Conflicting business evidence',

                        'description' => $conflict['message'],

                        'suggested_action' => 'Verify the conflicting information before relying on the current belief.',

                        'confidence' => $confidence,

                        'estimated_monthly_value' => null,

                        'priority_score' => $this->priority->score(
                            severity: 'high',
                            confidence: $confidence
                        ),

                        'source' => 'belief_conflict',

                        'source_reference' => $conflict['belief']->id,

                        'evidence' => [
                        'belief_key' => $conflict[
                                'belief'
                            ]->key,

                        'current_value' => $conflict[
                                'current_value'
                            ],

                        'contradiction_count' => $conflict[
                                'contradictions'
                            ]->count(),
                        ],
                    ];
                }
            );
    }

    private function gapFindings(
        $memory
    ): Collection {
        return $this->gaps
            ->gaps($memory)
            ->take(5)
            ->map(
                function (array $gap): array {
                    $severity =
                        $gap['priority'] >= 90
                            ? 'high'
                            : 'medium';

                    return [
                        'category' => 'knowledge_gap',

                        'severity' => $severity,

                        'title' => $gap['question'],

                        'description' => $gap['reason'],

                        'suggested_action' => 'Answer this question to improve Charlie\'s understanding.',

                        'confidence' => 100,

                        'estimated_monthly_value' => null,

                        'priority_score' => $this->priority->score(
                            severity: $severity,
                            confidence: 100
                        ),

                        'source' => $gap['source'],

                        'source_reference' => $gap['service_id']
                            ?? null,

                        'evidence' => $gap,
                    ];
                }
            );
    }

    private function insightFindings(
        string $memoryId
    ): Collection {
        return BusinessMemoryInsight::query()
            ->where(
                'business_memory_id',
                $memoryId
            )
            ->where(
                'status',
                'open'
            )
            ->get()
            ->map(
                function (
                    BusinessMemoryInsight $insight
                ): array {
                    $severity =
                        $insight->priority >= 85
                            ? 'high'
                            : (
                                $insight->priority >= 65
                                    ? 'medium'
                                    : 'low'
                            );

                    return [
                        'category' => $insight
                            ->insight_type
                            ->value,

                        'severity' => $severity,

                        'title' => $insight->title,

                        'description' => $insight->summary,

                        'suggested_action' => null,

                        'confidence' => $insight->confidence,

                        'estimated_monthly_value' => $insight->metadata[
                                'monthly_financial_impact'
                            ]
                            ?? null,

                        'priority_score' => $this->priority->score(
                            severity: $severity,
                            confidence: $insight->confidence,
                            monthlyValue: $insight->metadata[
                                    'monthly_financial_impact'
                                ]
                                ?? null
                        ),

                        'source' => 'business_memory_insight',

                        'source_reference' => $insight->id,

                        'evidence' => [
                        'insight_id' => $insight->id,
                        ],
                    ];
                }
            );
    }
}
