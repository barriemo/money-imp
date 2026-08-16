<?php

namespace App\Domains\BusinessBrain\Investigation\Cases;

use App\Domains\BusinessBrain\Experience\BusinessExperienceService;
use App\Models\InvestigationCase;
use App\Models\InvestigationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestigationCaseService
{
    public function findOrOpenForSubject(
        string $type,
        string $subjectType,
        string $subjectId,
        ?string $subjectName = null,
        ?string $title = null,
        ?string $question = null
    ): InvestigationCase {
        $existing =
            InvestigationCase::query()
                ->where(
                    'type',
                    $type
                )
                ->where(
                    'subject_type',
                    $subjectType
                )
                ->where(
                    'subject_id',
                    $subjectId
                )
                ->whereIn(
                    'status',
                    [
                        'open',
                        'testing',
                        'waiting',
                    ]
                )
                ->latest(
                    'opened_at'
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        return $this->open(
            type: $type,

            title: $title
                ?? sprintf(
                    '%s investigation',
                    $subjectName
                        ?? $subjectId
                ),

            question: $question,

            subjectType: $subjectType,

            subjectId: $subjectId,

            subjectName: $subjectName
        );
    }

    public function open(
        string $type,
        string $title,
        ?string $question = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectName = null,
        array $metadata = []
    ): InvestigationCase {
        return DB::transaction(
            function () use (
                $type,
                $title,
                $question,
                $subjectType,
                $subjectId,
                $subjectName,
                $metadata
            ): InvestigationCase {
                $case =
                    InvestigationCase::create([
                        'type' => $type,

                        'subject_type' => $subjectType,

                        'subject_id' => $subjectId,

                        'subject_name' => $subjectName,

                        'title' => $title,

                        'question' => $question,

                        'status' => 'open',

                        'confidence' => 0,

                        'opened_at' => now(),

                        'metadata' => $metadata,
                    ]);

                $this->event(
                    case: $case,
                    type: 'case_opened',
                    description: $question
                        ?? $title,
                    actorType: 'business_brain'
                );

                return $case;
            }
        );
    }

    public function claimAssessmentEvent(
        InvestigationCase $case,
        string $key,
        string $statement,
        string $status,
        int $confidence,
        array $evidence = [],
        array $eventMetadata = []
    ): InvestigationEvent {
        $previous =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'claim_assessed',
                        'claim_changed',
                    ]
                )
                ->where(
                    'payload->key',
                    $key
                )
                ->latest(
                    'occurred_at'
                )
                ->first();

        $previousStatus =
            $previous?->payload[
                'status'
            ] ?? null;

        $previousConfidence =
            $previous?->payload[
                'confidence'
            ] ?? null;

        $changed =
            $previous !== null
            && (
                $previousStatus !== $status
                || (int) $previousConfidence !== $confidence
            );

        if (
            $previous !== null
            && ! $changed
        ) {
            return $previous;
        }

        return $this->event(
            case: $case,

            type: $changed
                ? 'claim_changed'
                : 'claim_assessed',

            description: $changed
                ? sprintf(
                    '%s — %s (%d%%) → %s (%d%%)',
                    $statement,
                    $previousStatus,
                    $previousConfidence,
                    $status,
                    $confidence
                )
                : sprintf(
                    '%s — %s (%d%%)',
                    $statement,
                    $status,
                    $confidence
                ),

            payload: array_merge(
                $eventMetadata,
                [
                    'key' => $key,
                    'statement' => $statement,
                    'hypothesis_version' => $case->metadata['hypothesis_version']
                        ?? null,
                    'hypothesis_version' => $case->metadata['hypothesis_version']
                        ?? null,
                    'status' => $status,
                    'confidence' => $confidence,
                    'previous_status' => $previousStatus,
                    'previous_confidence' => $previousConfidence,
                    'evidence' => $evidence,
                ]
            )
        );
    }

    public function assessmentEvent(
        InvestigationCase $case,
        string $hypothesis,
        string $status,
        int $confidence,
        array $payload = [],
        array $eventMetadata = []
    ): InvestigationEvent {
        $previous =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'hypothesis_assessed',
                        'hypothesis_changed',
                    ]
                )
                ->latest(
                    'occurred_at'
                )
                ->first();

        $previousStatus =
            $previous?->payload[
                'status'
            ] ?? null;

        $previousConfidence =
            $previous?->payload[
                'confidence'
            ] ?? null;

        $changed =
            $previous !== null
            && (
                $previousStatus !== $status
                || (int) $previousConfidence !== $confidence
            );

        if (
            $previous !== null
            && ! $changed
        ) {
            return $previous;
        }

        return $this->event(
            case: $case,

            type: $changed
                ? 'hypothesis_changed'
                : 'hypothesis_assessed',

            description: $changed
                ? sprintf(
                    '%s — %s (%d%%) → %s (%d%%)',
                    $hypothesis,
                    $previousStatus,
                    $previousConfidence,
                    $status,
                    $confidence
                )
                : sprintf(
                    '%s — %s (%d%%)',
                    $hypothesis,
                    $status,
                    $confidence
                ),

            payload: array_merge(
                $payload,
                $eventMetadata,
                [
                    'status' => $status,
                    'confidence' => $confidence,
                    'previous_status' => $previousStatus,
                    'previous_confidence' => $previousConfidence,
                ]
            )
        );
    }

    public function correctHypothesis(
        InvestigationCase $case,
        string $hypothesis,
        string $reason,
        string $actorType = 'user'
    ): InvestigationCase {
        $previous =
            $case->current_hypothesis;

        if ($previous === $hypothesis) {
            return $case;
        }

        $hypothesisVersion =
            (string) Str::uuid();

        if ($previous !== null) {
            $this->event(
                case: $case,
                type: 'hypothesis_retracted',
                description: $previous,
                actorType: $actorType,
                payload: [
                    'reason' => $reason,
                    'replacement' => $hypothesis,
                ]
            );
        }

        $this->event(
            case: $case,
            type: 'hypothesis_asserted',
            description: $hypothesis,
            actorType: $actorType,
            payload: [
                'corrects' => $previous,
                'reason' => $reason,
                'hypothesis_version' => $hypothesisVersion,
            ]
        );

        $metadata =
            $case->metadata
            ?? [];

        $metadata['hypothesis_version'] =
            $hypothesisVersion;

        $case->forceFill([
            'current_hypothesis' => $hypothesis,
            'status' => 'testing',
            'verdict' => null,
            'metadata' => $metadata,
        ])->save();

        return $case->refresh();
    }

    public function close(
        InvestigationCase $case,
        string $verdict,
        int $confidence,
        string $reason = 'Investigation verified.',
        array $eventMetadata = []
    ): InvestigationCase {
        if ($case->status === 'closed') {
            return $case;
        }

        $this->event(
            case: $case,

            type: 'case_closed',

            description: $reason,

            payload: array_merge(
                $eventMetadata,
                [
                    'verdict' => $verdict,
                    'confidence' => $confidence,
                ]
            )
        );

        $case->forceFill([
            'status' => 'closed',
            'confidence' => $confidence,
            'verdict' => $verdict,
            'closed_at' => now(),
        ])->save();

        $case =
            $case->refresh();

        app(
            BusinessExperienceService::class
        )->capture(
            $case
        );

        return $case;
    }

    public function event(
        InvestigationCase $case,
        string $type,
        string $description,
        string $actorType = 'business_brain',
        array $payload = []
    ): InvestigationEvent {
        return $case
            ->events()
            ->create([
                'type' => $type,

                'actor_type' => $actorType,

                'description' => $description,

                'payload' => $payload,

                'occurred_at' => now(),
            ]);
    }
}
