<?php

namespace App\Domains\TimelineTruth;

use App\Models\TimelineEvent;

class ChangeDetectionService
{
    public function __construct(
        private TimelineRecorder $timeline
    ) {}

    public function detect(
        string $subjectType,
        string $subjectId,
        string $field,
        mixed $before,
        mixed $after,
        string $source,
        ?int $confidenceBefore = null,
        ?int $confidenceAfter = null,
        array $metadata = []
    ): ?TimelineEvent {
        if (
            $this->normalise(
                $before
            )
            ===
            $this->normalise(
                $after
            )
            &&
            $confidenceBefore
            ===
            $confidenceAfter
        ) {
            return null;
        }

        return $this->timeline
            ->record(
                subjectType: $subjectType,

                subjectId: $subjectId,

                eventType: 'truth_changed',

                source: $source,

                summary: $this->summary(
                    $field,
                    $before,
                    $after,
                    $confidenceBefore,
                    $confidenceAfter
                ),

                field: $field,

                before: $before,

                after: $after,

                confidenceBefore: $confidenceBefore,

                confidenceAfter: $confidenceAfter,

                metadata: $metadata
            );
    }

    private function normalise(
        mixed $value
    ): mixed {
        if (is_string($value)) {
            return trim(
                strtolower(
                    $value
                )
            );
        }

        return $value;
    }

    private function summary(
        string $field,
        mixed $before,
        mixed $after,
        ?int $confidenceBefore,
        ?int $confidenceAfter
    ): string {
        $summary =
            $field
            .' changed from '
            .var_export(
                $before,
                true
            )
            .' to '
            .var_export(
                $after,
                true
            )
            .'.';

        if (
            $confidenceBefore !== null
            || $confidenceAfter !== null
        ) {
            $summary .=
                ' Confidence changed from '
                .($confidenceBefore ?? 0)
                .'% to '
                .($confidenceAfter ?? 0)
                .'%.';
        }

        return $summary;
    }
}
