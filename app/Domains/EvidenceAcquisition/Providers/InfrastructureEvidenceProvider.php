<?php

namespace App\Domains\EvidenceAcquisition\Providers;

use App\Domains\EvidenceAcquisition\Contracts\EvidenceQuestionProvider;
use App\Domains\EvidenceAcquisition\EvidenceQuestion;
use App\Domains\Infrastructure\Attribution\HostingKnowledgeGapService;
use Illuminate\Support\Collection;

class InfrastructureEvidenceProvider implements EvidenceQuestionProvider
{
    public function __construct(
        private HostingKnowledgeGapService $gaps
    ) {}

    public function questions(): Collection
    {
        return $this->gaps
            ->gaps()
            ->map(
                function (array $gap): EvidenceQuestion {
                    return new EvidenceQuestion(
                        question: 'Which server hosts this client?',

                        reason: 'Hosting revenue exists but infrastructure ownership is unknown.',

                        priority: 0,

                        domain: 'infrastructure',

                        evidence: [
                            'client_id' => $gap['client_id'],

                            'monthly_revenue' => $gap['monthly_revenue'],

                            'confidence' => $gap['confidence'],

                            'evidence_count' => $gap['evidence_count'],
                        ]
                    );
                }
            );
    }
}
