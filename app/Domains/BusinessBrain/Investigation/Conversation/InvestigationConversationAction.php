<?php

namespace App\Domains\BusinessBrain\Investigation\Conversation;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Investigation\Timeline\InvestigationTimelinePresenter;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationConversationAction
{
    public function __construct(
        private InvestigationTimelinePresenter $timeline
    ) {}

    public function execute(
        string $question,
        ConversationContext $context
    ): ?BusinessResponse {
        $intent =
            $this->intent(
                $question,
                $context
            );

        if ($intent === null) {
            return null;
        }

        $case =
            $this->resolveCase(
                $question,
                $context
            );

        if (! $case) {
            return new BusinessResponse(
                answer: 'I could not find an investigation matching that request.',
                context: $context
            );
        }

        $this->applyCaseToContext(
            $case,
            $context
        );

        return match ($intent) {
            'explain_change' => $this->explainChange(
                $case,
                $context
            ),

            'show_missing_evidence' => $this->showMissingEvidence(
                $case,
                $context
            ),

            'show_latest_conclusion' => $this->showLatestConclusion(
                $case,
                $context
            ),

            default => $this->showTimeline(
                $case,
                $context
            ),
        };
    }

    private function showTimeline(
        InvestigationCase $case,
        ConversationContext $context
    ): BusinessResponse {
        return new BusinessResponse(
            answer: $this->timeline
                ->present(
                    $case
                ),
            context: $context,
            questions: [
                'Why did your conclusion change?',
                'What evidence is still missing?',
                'Show me the latest conclusion.',
            ]
        );
    }

    private function explainChange(
        InvestigationCase $case,
        ConversationContext $context
    ): BusinessResponse {
        $latestTrigger =
            $case->events()
                ->where(
                    'type',
                    'evidence_changed'
                )
                ->latest(
                    'occurred_at'
                )
                ->first();

        if ($latestTrigger) {
            $triggerTime =
                $latestTrigger->occurred_at;

            $changes =
                $case->events()
                    ->whereIn(
                        'type',
                        [
                            'claim_changed',
                            'hypothesis_changed',
                            'case_closed',
                        ]
                    )
                    ->where(
                        'occurred_at',
                        '>=',
                        $triggerTime
                    )
                    ->orderBy(
                        'occurred_at'
                    )
                    ->get();

            $answer =
                sprintf(
                    "My conclusion changed after this new evidence arrived:\n\n%s",
                    $latestTrigger->description
                );

            $claimChanges =
                $changes
                    ->where(
                        'type',
                        'claim_changed'
                    );

            if ($claimChanges->isNotEmpty()) {
                $lines =
                    $claimChanges
                        ->map(
                            function ($event): string {
                                $payload =
                                    $event->payload
                                    ?? [];

                                return sprintf(
                                    '- %s: %s (%d%%) → %s (%d%%)',
                                    $payload['statement']
                                        ?? $event->description,
                                    strtoupper(
                                        (string) (
                                            $payload['previous_status']
                                            ?? 'unknown'
                                        )
                                    ),
                                    (int) (
                                        $payload['previous_confidence']
                                        ?? 0
                                    ),
                                    strtoupper(
                                        (string) (
                                            $payload['status']
                                            ?? 'unknown'
                                        )
                                    ),
                                    (int) (
                                        $payload['confidence']
                                        ?? 0
                                    )
                                );
                            }
                        )
                        ->implode(
                            PHP_EOL
                        );

                $answer .=
                    "\n\nThat changed these claim positions:\n"
                    .$lines;
            }

            $hypothesisChange =
                $changes
                    ->firstWhere(
                        'type',
                        'hypothesis_changed'
                    );

            if ($hypothesisChange) {
                $payload =
                    $hypothesisChange->payload
                    ?? [];

                $answer .=
                    sprintf(
                        "\n\nThe overall hypothesis then moved from %s at %d%% confidence to %s at %d%% confidence.",
                        strtoupper(
                            (string) (
                                $payload['previous_status']
                                ?? 'unknown'
                            )
                        ),
                        (int) (
                            $payload['previous_confidence']
                            ?? 0
                        ),
                        strtoupper(
                            (string) (
                                $payload['status']
                                ?? 'unknown'
                            )
                        ),
                        (int) (
                            $payload['confidence']
                            ?? 0
                        )
                    );
            }

            $closed =
                $changes
                    ->firstWhere(
                        'type',
                        'case_closed'
                    );

            if ($closed) {
                $answer .=
                    sprintf(
                        "\n\nThe investigation was then closed: %s",
                        $closed->description
                    );
            }

            return new BusinessResponse(
                answer: $answer,
                context: $context,
                questions: [
                    'What evidence is still missing?',
                    'Show me the latest conclusion.',
                    'Show me the full investigation.',
                ]
            );
        }

        $events =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'hypothesis_changed',
                        'claim_changed',
                    ]
                )
                ->orderBy(
                    'occurred_at'
                )
                ->get();

        if ($events->isNotEmpty()) {
            $lines =
                $events
                    ->map(
                        fn ($event) => '- '.$event->description
                    )
                    ->implode(
                        PHP_EOL
                    );

            return new BusinessResponse(
                answer: "My recorded conclusion changed because the evidence changed:\n\n"
                    .$lines,
                context: $context,
                questions: [
                    'What evidence is still missing?',
                    'Show me the latest conclusion.',
                    'Show me the full investigation.',
                ]
            );
        }

        /*
         * Older investigations may pre-date explicit *_changed events.
         * Recover their evolution from distinct hypothesis assessments
         * without rewriting the historical audit trail.
         */
        $assessments =
            $case->events()
                ->where(
                    'type',
                    'hypothesis_assessed'
                )
                ->orderBy(
                    'occurred_at'
                )
                ->get()
                ->map(
                    fn ($event) => [
                        'status' => $event->payload['status']
                            ?? null,

                        'confidence' => $event->payload['confidence']
                            ?? null,

                        'missing_evidence' => $event->payload['missing_evidence']
                            ?? [],
                    ]
                )
                ->unique(
                    fn (array $assessment) => ($assessment['status'] ?? '')
                        .'|'
                        .($assessment['confidence'] ?? '')
                )
                ->values();

        if ($assessments->count() >= 2) {
            $first =
                $assessments->first();

            $last =
                $assessments->last();

            $answer =
                sprintf(
                    'The recorded conclusion moved from %s at %d%% confidence to %s at %d%% confidence.',
                    strtoupper(
                        (string) $first['status']
                    ),
                    (int) $first['confidence'],
                    strtoupper(
                        (string) $last['status']
                    ),
                    (int) $last['confidence']
                );

            $missing =
                collect(
                    $last['missing_evidence']
                    ?? []
                )
                    ->filter()
                    ->values();

            if ($missing->isNotEmpty()) {
                $answer .=
                    "\n\nThe later assessment identified these unresolved evidence gaps:\n\n"
                    .$missing
                        ->values()
                        ->map(
                            fn ($item, $index) => sprintf(
                                '%d. %s',
                                $index + 1,
                                $item
                            )
                        )
                        ->implode(
                            PHP_EOL
                        );
            }

            $answer .=
                "\n\nThis part of the case predates explicit belief-change events, so I have reconstructed the change from the recorded assessments rather than rewriting the historical timeline.";

            return new BusinessResponse(
                answer: $answer,
                context: $context,
                questions: [
                    'What evidence is still missing?',
                    'Show me the latest conclusion.',
                ]
            );
        }

        return new BusinessResponse(
            answer: 'I do not currently have a recorded change in conclusion for this investigation.',
            context: $context,
            questions: [
                'Show me the latest conclusion.',
                'Show me the full investigation.',
            ]
        );
    }

    private function showMissingEvidence(
        InvestigationCase $case,
        ConversationContext $context
    ): BusinessResponse {
        $event =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'hypothesis_changed',
                        'hypothesis_assessed',
                    ]
                )
                ->latest(
                    'occurred_at'
                )
                ->get()
                ->first(
                    fn ($event) => collect(
                        $event->payload['missing_evidence']
                        ?? []
                    )->isNotEmpty()
                );

        $missing =
            collect(
                $event?->payload['missing_evidence']
                ?? []
            )
                ->filter()
                ->values();

        if ($missing->isEmpty()) {
            return new BusinessResponse(
                answer: 'The latest recorded investigation assessment does not contain any explicit missing-evidence items.',
                context: $context,
                questions: [
                    'Show me the latest conclusion.',
                    'Show me the full investigation.',
                ]
            );
        }

        $lines =
            $missing
                ->map(
                    fn ($item, $index) => sprintf(
                        '%d. %s',
                        $index + 1,
                        $item
                    )
                )
                ->implode(
                    PHP_EOL
                );

        return new BusinessResponse(
            answer: "The investigation is still missing this evidence:\n\n"
                .$lines,
            context: $context,
            questions: [
                'Why did your conclusion change?',
                'Show me the latest conclusion.',
            ]
        );
    }

    private function showLatestConclusion(
        InvestigationCase $case,
        ConversationContext $context
    ): BusinessResponse {
        $claims =
            $this->latestClaims(
                $case
            );

        $answer =
            sprintf(
                '%s is currently %s at %d%% confidence.',
                $case->subject_name
                    ?? 'This investigation',
                strtoupper(
                    $case->status
                ),
                (int) $case->confidence
            );

        if ($case->current_hypothesis) {
            $answer .=
                sprintf(
                    "\n\nCurrent hypothesis:\n%s",
                    $case->current_hypothesis
                );
        }

        if ($case->verdict) {
            $answer .=
                sprintf(
                    "\n\nCurrent verdict:\n%s",
                    $case->verdict
                );
        }

        if ($claims->isNotEmpty()) {
            $claimLines =
                $claims
                    ->map(
                        function ($event): string {
                            $payload =
                                $event->payload
                                ?? [];

                            $confidence =
                                (int) (
                                    $payload['confidence']
                                    ?? 0
                                );

                            $confidenceLabel =
                                $confidence > 0
                                    ? sprintf(
                                        ' — %d%%',
                                        $confidence
                                    )
                                    : '';

                            return sprintf(
                                '- %s%s: %s',
                                strtoupper(
                                    (string) (
                                        $payload['status']
                                        ?? 'unknown'
                                    )
                                ),
                                $confidenceLabel,
                                $payload['statement']
                                    ?? $event->description
                            );
                        }
                    )
                    ->implode(
                        PHP_EOL
                    );

            $answer .=
                "\n\nCurrent claim positions:\n"
                .$claimLines;
        }

        return new BusinessResponse(
            answer: $answer,
            context: $context,
            questions: [
                'What evidence is still missing?',
                'Why did your conclusion change?',
                'Show me the full investigation.',
            ]
        );
    }

    private function latestClaims(
        InvestigationCase $case
    ): Collection {
        $hypothesisVersion =
            $case->metadata['hypothesis_version']
            ?? null;

        $events =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'claim_assessed',
                        'claim_changed',
                    ]
                )
                ->orderBy(
                    'occurred_at'
                )
                ->get();

        /*
         * Once a hypothesis has an explicit version, only claims
         * assessed against that exact hypothesis remain current.
         *
         * Older unversioned claims remain part of the immutable
         * investigation history, but not current belief.
         */
        if ($hypothesisVersion !== null) {
            $events =
                $events->filter(
                    fn ($event) => (
                        $event->payload[
                            'hypothesis_version'
                        ]
                        ?? null
                    ) === $hypothesisVersion
                );
        }

        return $events
            ->filter(
                fn ($event) => isset(
                    $event->payload['key']
                )
            )
            ->groupBy(
                fn ($event) => $event->payload['key']
            )
            ->map(
                fn (Collection $events) => $events->last()
            )
            ->values();
    }

    private function applyCaseToContext(
        InvestigationCase $case,
        ConversationContext $context
    ): void {
        $context->subjectType =
            $case->subject_type;

        $context->subjectId =
            $case->subject_id;

        $context->subjectName =
            $case->subject_name;

        $context->investigationCaseId =
            $case->id;

        $context->issue =
            'investigation_history';
    }

    private function intent(
        string $question,
        ConversationContext $context
    ): ?string {
        $question =
            strtolower(
                trim(
                    $question
                )
            );

        if (
            str_contains(
                $question,
                'why did your conclusion change'
            )
            || str_contains(
                $question,
                'why did the conclusion change'
            )
            || str_contains(
                $question,
                'why did your assessment change'
            )
        ) {
            return 'explain_change';
        }

        if (
            str_contains(
                $question,
                'what evidence is still missing'
            )
            || str_contains(
                $question,
                'what evidence is missing'
            )
            || str_contains(
                $question,
                'missing evidence'
            )
        ) {
            return 'show_missing_evidence';
        }

        if (
            str_contains(
                $question,
                'latest conclusion'
            )
            || str_contains(
                $question,
                'current conclusion'
            )
            || str_contains(
                $question,
                'current verdict'
            )
        ) {
            return 'show_latest_conclusion';
        }

        if (
            $this->isInvestigationQuestion(
                $question
            )
        ) {
            return 'show_timeline';
        }

        if (
            $context->investigationCaseId
            && (
                str_contains(
                    $question,
                    'show me everything'
                )
                || str_contains(
                    $question,
                    'full investigation'
                )
            )
        ) {
            return 'show_timeline';
        }

        return null;
    }

    private function resolveCase(
        string $question,
        ConversationContext $context
    ): ?InvestigationCase {
        $explicitSubject =
            $this->explicitInvestigationSubject(
                $question
            );

        /*
         * An explicitly named subject in the current question always
         * outranks conversational context.
         *
         * If the user names a subject we cannot resolve, do not silently
         * substitute the previously selected investigation.
         */
        if ($explicitSubject !== null) {
            return InvestigationCase::query()
                ->whereRaw(
                    'LOWER(subject_name) LIKE ?',
                    [
                        '%'.strtolower($explicitSubject).'%',
                    ]
                )
                ->latest(
                    'opened_at'
                )
                ->first();
        }

        if ($context->investigationCaseId) {
            $case =
                InvestigationCase::query()
                    ->find(
                        $context->investigationCaseId
                    );

            if ($case) {
                return $case;
            }
        }

        if ($context->subjectId) {
            $case =
                InvestigationCase::query()
                    ->where(
                        'subject_type',
                        $context->subjectType
                            ?? 'client'
                    )
                    ->where(
                        'subject_id',
                        $context->subjectId
                    )
                    ->latest(
                        'opened_at'
                    )
                    ->first();

            if ($case) {
                return $case;
            }
        }

        return null;
    }

    private function explicitInvestigationSubject(
        string $question
    ): ?string {
        $question =
            trim(
                $question
            );

        $patterns = [
            '/(?:can\s+you\s+)?show\s+me(?:\s+the)?\s+(.+?)\s+investigation\b/i',
            '/tell\s+me\s+about\s+(.+?)\s+investigation\b/i',
            '/open(?:\s+the)?\s+(.+?)\s+investigation\b/i',
            '/what\s+happened\s+(?:with|to)\s+(.+?)(?:\s+investigation)?[?.!]*$/i',
        ];

        foreach ($patterns as $pattern) {
            if (
                ! preg_match(
                    $pattern,
                    $question,
                    $matches
                )
            ) {
                continue;
            }

            $subject =
                strtolower(
                    trim(
                        $matches[1],
                        " \t\n\r\0\x0B?.!"
                    )
                );

            if (
                $subject === ''
                || in_array(
                    $subject,
                    [
                        'this',
                        'that',
                        'it',
                        'investigation',
                        'this investigation',
                        'that investigation',
                        'full',
                        'latest',
                        'current',
                        'the full',
                        'the latest',
                        'the current',
                    ],
                    true
                )
            ) {
                return null;
            }

            return $subject;
        }

        return null;
    }

    private function isInvestigationQuestion(
        string $question
    ): bool {
        return
            str_contains(
                $question,
                'investigation'
            )
            || str_contains(
                $question,
                'what happened with'
            )
            || str_contains(
                $question,
                'what happened to'
            );
    }
}
