<?php

namespace App\Domains\BusinessMemory\Theories;

use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryTheory;
use App\Models\BusinessMemoryTheoryEvidence;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessMemoryTheoryService
{
    public function rebuild(
        BusinessMemory $memory
    ): Collection {
        $observations = $memory
            ->entries()
            ->with('observations')
            ->get()
            ->flatMap(
                fn ($entry) => $entry->observations
            );

        $theories = collect();

        $expansionEvidence =
            $observations->filter(
                function ($observation): bool {
                    $text = Str::lower(
                        $observation->statement
                    );

                    return in_array(
                        $observation->observation_type,
                        [
                            BusinessMemoryObservationType::Requirement,
                            BusinessMemoryObservationType::Opportunity,
                            BusinessMemoryObservationType::Fact,
                        ],
                        true
                    )
                    && (
                        str_contains(
                            $text,
                            'another location'
                        )
                        || str_contains(
                            $text,
                            'second location'
                        )
                        || str_contains(
                            $text,
                            'new office'
                        )
                        || str_contains(
                            $text,
                            'opening'
                        )
                        || str_contains(
                            $text,
                            'expanding'
                        )
                    );
                }
            );

        if ($expansionEvidence->count() >= 2) {
            $theory =
                BusinessMemoryTheory::updateOrCreate(
                    [
                        'business_memory_id' => $memory->id,

                        'theory_type' => 'business_expansion',
                    ],
                    [
                        'statement' => 'Client appears to be expanding to another location.',

                        'confidence' => min(
                            95,
                            60
                            + (
                                $expansionEvidence
                                    ->count()
                                * 10
                            )
                        ),

                        'status' => 'active',

                        'verified' => false,

                        'source' => 'rule',
                    ]
                );

            foreach (
                $expansionEvidence as $observation
            ) {
                BusinessMemoryTheoryEvidence::updateOrCreate(
                    [
                        'business_memory_theory_id' => $theory->id,

                        'business_memory_observation_id' => $observation->id,
                    ],
                    [
                        'weight' => $observation
                            ->confidence,

                        'relationship' => 'supports',
                    ]
                );
            }

            $theories->push(
                $theory
            );
        }

        return $theories;
    }
}
