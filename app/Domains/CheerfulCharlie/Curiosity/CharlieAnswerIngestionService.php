<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\ManagedServices\Knowledge\ManagedServiceComponentKnowledgeService;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use App\Models\ManagedService;

class CharlieAnswerIngestionService
{
    public function __construct(
        private BusinessContextService $context,
        private ManagedServiceComponentKnowledgeService $componentKnowledge
    ) {}

    public function ingest(
        BusinessMemory $memory,
        array $question,
        string $answer,
        int $confidence = 100
    ): BusinessContext {
        $context =
            $this->context->remember(
                memory: $memory,
                type: $question['type'],
                key: $question['key'],
                value: trim($answer),
                confidence: $confidence,
                verified: true,
                source: 'charlie_answer'
            );

        if (
            isset(
                $question['service_id'],
                $question['component_type']
            )
        ) {
            $service =
                ManagedService::query()
                    ->find(
                        $question[
                            'service_id'
                        ]
                    );

            if ($service) {
                $this->componentKnowledge
                    ->remember(
                        service: $service,
                        componentType: $question[
                                'component_type'
                            ],
                        value: trim($answer),
                        state: 'externally_managed',
                        confidence: $confidence,
                        verified: false,
                        source: 'charlie_answer',
                        sourceReference: $context->id
                    );
            }
        }

        return $context;
    }
}
