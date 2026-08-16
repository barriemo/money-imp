<?php

namespace App\Domains\BusinessBrain\Experience;

use App\Models\BusinessExperience;
use App\Models\InvestigationCase;

class BusinessExperienceService
{
    public function capture(
        InvestigationCase $case
    ): BusinessExperience {
        $case->loadMissing(
            'events'
        );

        $fingerprint =
            $this->fingerprint(
                $case
            );

        return BusinessExperience::query()
            ->firstOrCreate(
                [
                    'source_investigation_case_id' => $case->id,
                ],
                [
                    'fingerprint' => $fingerprint,

                    'type' => $case->type,

                    'subject_type' => $case->subject_type,

                    'subject_id' => $case->subject_id,

                    'subject_name' => $case->subject_name,

                    'title' => $case->title,

                    'summary' => $this->summary(
                        $case
                    ),

                    'outcome' => $case->verdict,

                    'confidence' => (int) $case->confidence,

                    'importance' => $this->importance(
                        $case
                    ),

                    'hypothesis' => $case->current_hypothesis,

                    'lessons' => $this->lessons(
                        $case
                    ),

                    'evidence_summary' => $this->evidenceSummary(
                        $case
                    ),

                    'metadata' => [
                    'investigation_status' => $case->status,

                    'opened_at' => $case->opened_at
                        ?->toISOString(),

                    'closed_at' => $case->closed_at
                        ?->toISOString(),
                    ],

                    'experienced_at' => $case->closed_at
                        ?? now(),
                ]
            );
    }

    private function fingerprint(
        InvestigationCase $case
    ): string {
        return hash(
            'sha256',
            implode(
                '|',
                [
                    'investigation',
                    $case->id,
                    $case->type,
                    $case->subject_type
                        ?? '',
                    $case->subject_id
                        ?? '',
                ]
            )
        );
    }

    private function summary(
        InvestigationCase $case
    ): string {
        if ($case->verdict) {
            return sprintf(
                '%s %s',
                $case->subject_name
                    ?? 'The investigation',
                $case->verdict
            );
        }

        return $case->title;
    }

    private function lessons(
        InvestigationCase $case
    ): array {
        return [
            'question' => $case->question
                ?? $case->title,

            'hypothesis' => $case->current_hypothesis,

            'outcome' => $case->verdict,

            'confidence' => (int) $case->confidence,
        ];
    }

    private function evidenceSummary(
        InvestigationCase $case
    ): array {
        return $case->events
            ->filter(
                fn ($event) => in_array(
                    $event->type,
                    [
                        'evidence_changed',
                        'claim_changed',
                        'hypothesis_changed',
                        'case_closed',
                    ],
                    true
                )
            )
            ->map(
                fn ($event) => [
                    'type' => $event->type,
                    'description' => $event->description,
                    'correlation_id' => $event->payload[
                            'correlation_id'
                        ]
                        ?? null,
                    'occurred_at' => $event->occurred_at
                        ?->toISOString(),
                ]
            )
            ->values()
            ->all();
    }

    private function importance(
        InvestigationCase $case
    ): int {
        if ($case->confidence >= 90) {
            return 80;
        }

        if ($case->confidence >= 70) {
            return 65;
        }

        return 50;
    }
}
