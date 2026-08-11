<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefService;
use App\Domains\ManagedServices\Knowledge\ManagedServiceComponentKnowledgeService;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use App\Models\ManagedService;

class CharlieAnswerIngestionService
{
    public function __construct(
        private BusinessContextService $context,
        private ManagedServiceComponentKnowledgeService $componentKnowledge,
        private BusinessBeliefService $beliefs
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

        $subject =
            $memory->subject;

        if ($subject) {
            $belief =
                $this->beliefs->remember(
                    subject: $subject,
                    beliefType: $this->beliefType(
                        $question
                    ),
                    key: $question['key'],
                    value: trim($answer),
                    source: 'charlie_answer'
                );

            $this->beliefs->addEvidence(
                belief: $belief,
                evidence: $context,
                relationship: 'supports',
                weight: 90,
                confidence: $confidence,
                summary: 'Answer supplied directly through Cheerful Charlie.'
            );
        }

        if (
            isset(
                $question['service_id'],
                $question['component_type']
            )
        ) {
            $service =
                ManagedService::query()
                    ->find(
                        $question['service_id']
                    );

            if ($service) {
                $knowledge =
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

                if ($subject) {
                    $belief =
                        $this->beliefs->remember(
                            subject: $service,
                            beliefType: 'managed_service_component',
                            key: $question[
                                    'component_type'
                                ],
                            value: trim($answer),
                            source: 'charlie_answer'
                        );

                    $this->beliefs->addEvidence(
                        belief: $belief,
                        evidence: $knowledge,
                        relationship: 'supports',
                        weight: 95,
                        confidence: $confidence,
                        summary: 'Managed service component knowledge derived from Charlie answer.'
                    );
                }
            }
        }

        return $context;
    }

    private function beliefType(
        array $question
    ): string {
        if (
            isset(
                $question[
                    'component_type'
                ]
            )
        ) {
            return 'service_provider';
        }

        return 'business_context';
    }
}
