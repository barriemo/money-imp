<?php

namespace App\Domains\EvidenceAcquisition\Ranking;

use App\Domains\EvidenceAcquisition\EvidenceQuestion;
use App\Domains\EvidenceAcquisition\Scoring\EvidencePriorityCalculator;
use Illuminate\Support\Collection;

class EvidenceQueueBuilder
{
    public function __construct(
        private EvidencePriorityCalculator $calculator
    ) {}

    public function build(
        Collection $questions
    ): Collection {
        return $questions
            ->map(
                function (
                    EvidenceQuestion $question
                ): EvidenceQuestion {
                    $priority =
                        $this->calculator->calculate(
                            impact: $question->evidence['impact'] ?? 50,

                            confidence: $question->evidence['confidence'] ?? 0,

                            financialValue: $question->evidence['financial_value'] ?? 0,

                            urgency: $question->evidence['urgency'] ?? 0,
                        );

                    return new EvidenceQuestion(
                        question: $question->question,

                        reason: $question->reason,

                        priority: $priority,

                        domain: $question->domain,

                        evidence: $question->evidence
                    );
                }
            )
            ->sortByDesc(
                fn (
                    EvidenceQuestion $question
                ) => $question->priority
            )
            ->values();
    }
}
