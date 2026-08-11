<?php

namespace App\Domains\CheerfulCharlie\Beliefs;

use App\Models\BusinessBelief;
use App\Models\BusinessBeliefEvidence;
use Illuminate\Database\Eloquent\Model;

class BusinessBeliefService
{
    public function __construct(
        private BusinessBeliefConfidenceService $confidence
    ) {}

    public function remember(
        Model $subject,
        string $beliefType,
        string $key,
        ?string $value,
        string $source = 'derived',
        bool $verified = false,
        array $metadata = []
    ): BusinessBelief {
        return BusinessBelief::updateOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),

                'subject_id' => $subject->getKey(),

                'belief_type' => $beliefType,

                'key' => $key,
            ],
            [
                'value' => $value,

                'verified' => $verified,

                'source' => $source,

                'status' => 'active',

                'metadata' => $metadata,
            ]
        );
    }

    public function addEvidence(
        BusinessBelief $belief,
        Model $evidence,
        string $relationship = 'supports',
        int $weight = 50,
        int $confidence = 50,
        ?string $summary = null,
        array $metadata = []
    ): BusinessBeliefEvidence {
        $item =
            BusinessBeliefEvidence::updateOrCreate(
                [
                    'business_belief_id' => $belief->id,

                    'evidence_type' => $evidence->getMorphClass(),

                    'evidence_id' => $evidence->getKey(),

                    'relationship' => $relationship,
                ],
                [
                    'weight' => max(
                        0,
                        min(
                            100,
                            $weight
                        )
                    ),

                    'confidence' => max(
                        0,
                        min(
                            100,
                            $confidence
                        )
                    ),

                    'summary' => $summary,

                    'metadata' => $metadata,
                ]
            );

        $belief->refresh();

        $belief->update([
            'confidence' => $this->confidence
                ->calculate(
                    $belief
                ),
        ]);

        return $item;
    }
}
