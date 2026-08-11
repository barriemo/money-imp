<?php

namespace App\Domains\BusinessMemory\Insights;

use App\Domains\BusinessMemory\Enums\BusinessMemoryInsightType;
use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryInsight;
use App\Models\BusinessMemoryTheory;
use Illuminate\Support\Collection;

class BusinessMemoryInsightService
{
    public function rebuild(
        BusinessMemory $memory
    ): Collection {
        $insights = collect();

        $theories = $memory
            ->hasMany(
                BusinessMemoryTheory::class
            )
            ->where(
                'status',
                'active'
            )
            ->get();

        foreach ($theories as $theory) {
            if (
                $theory->theory_type
                === 'business_expansion'
            ) {
                $insights->push(
                    BusinessMemoryInsight::updateOrCreate(
                        [
                            'business_memory_id' => $memory->id,

                            'business_memory_theory_id' => $theory->id,

                            'insight_type' => BusinessMemoryInsightType::Opportunity->value,
                        ],
                        [
                            'title' => 'Review expansion requirements',

                            'summary' => 'Client expansion may require CRM, infrastructure, connectivity, licences and service review.',

                            'confidence' => $theory->confidence,

                            'priority' => 80,

                            'status' => 'open',

                            'source' => 'theory_rule',
                        ]
                    )
                );
            }
        }

        $observations = $memory
            ->entries()
            ->with('observations')
            ->get()
            ->flatMap(
                fn ($entry) => $entry->observations
            );

        foreach ($observations as $observation) {
            $definition =
                $this->definitionFor(
                    $observation->observation_type
                );

            if (! $definition) {
                continue;
            }

            [$type, $title, $priority] =
                $definition;

            $insights->push(
                BusinessMemoryInsight::updateOrCreate(
                    [
                        'business_memory_id' => $memory->id,

                        'business_memory_observation_id' => $observation->id,

                        'insight_type' => $type->value,
                    ],
                    [
                        'title' => $title,

                        'summary' => $observation->statement,

                        'confidence' => $observation->confidence,

                        'priority' => $priority,

                        'status' => 'open',

                        'source' => 'observation_rule',
                    ]
                )
            );
        }

        return $insights
            ->unique('id')
            ->values();
    }

    private function definitionFor(
        BusinessMemoryObservationType $type
    ): ?array {
        return match ($type) {
            BusinessMemoryObservationType::Risk => [
                BusinessMemoryInsightType::Risk,
                'Operational risk requires review',
                90,
            ],

            BusinessMemoryObservationType::Promise => [
                BusinessMemoryInsightType::FollowUp,
                'Promise requires follow-up',
                95,
            ],

            BusinessMemoryObservationType::Opportunity => [
                BusinessMemoryInsightType::Opportunity,
                'Commercial opportunity identified',
                80,
            ],

            BusinessMemoryObservationType::Question => [
                BusinessMemoryInsightType::Question,
                'Open question requires an answer',
                60,
            ],

            default => null,
        };
    }

    private function semanticKey(
        string $type,
        string $title,
        string $summary
    ): string {
        return hash(
            'sha256',
            implode('|', [
                strtolower(trim($type)),
                strtolower(trim($title)),
                strtolower(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        trim($summary)
                    ) ?? trim($summary)
                ),
            ])
        );
    }
}
