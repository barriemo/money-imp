<?php

namespace App\Domains\TimelineTruth;

use App\Models\TimelineEvent;

class TimelineRecorder
{
    public function record(
        string $subjectType,
        string $subjectId,
        string $eventType,
        string $source,
        string $summary,
        ?string $field = null,
        mixed $before = null,
        mixed $after = null,
        ?int $confidenceBefore = null,
        ?int $confidenceAfter = null,
        array $metadata = []
    ): TimelineEvent {
        return TimelineEvent::create([
            'subject_type' => $subjectType,

            'subject_id' => $subjectId,

            'event_type' => $eventType,

            'source' => $source,

            'field' => $field,

            'before' => $this->wrap(
                $before
            ),

            'after' => $this->wrap(
                $after
            ),

            'confidence_before' => $confidenceBefore,

            'confidence_after' => $confidenceAfter,

            'summary' => $summary,

            'metadata' => $metadata,

            'occurred_at' => now(),
        ]);
    }

    private function wrap(
        mixed $value
    ): ?array {
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
        ];
    }
}
