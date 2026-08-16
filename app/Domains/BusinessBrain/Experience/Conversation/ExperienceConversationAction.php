<?php

namespace App\Domains\BusinessBrain\Experience\Conversation;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Experience\Matching\BusinessExperienceMatcher;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use App\Models\InvestigationCase;

class ExperienceConversationAction
{
    public function __construct(
        private BusinessExperienceMatcher $matcher
    ) {}

    public function execute(
        string $question,
        ConversationContext $context
    ): ?BusinessResponse {
        $intent =
            $this->intent(
                $question
            );

        if ($intent === null) {
            return null;
        }

        $case =
            $this->resolveInvestigation(
                $context
            );

        if (! $case) {
            return new BusinessResponse(
                answer: 'I need a current investigation before I can compare it with previous experience.',
                context: $context
            );
        }

        $matches =
            $this->matcher
                ->forInvestigation(
                    $case
                );

        if ($matches->isEmpty()) {
            return new BusinessResponse(
                answer: 'I do not currently have any previous business experiences similar enough to this investigation.',
                context: $context,
                questions: [
                    'Show me the current investigation.',
                    'What evidence is still missing?',
                ]
            );
        }

        return match ($intent) {
            'what_solved_before' => $this->whatSolvedBefore(
                $matches,
                $context
            ),

            default => $this->showSimilar(
                $matches,
                $context
            ),
        };
    }

    private function showSimilar(
        $matches,
        ConversationContext $context
    ): BusinessResponse {
        $lines = [
            sprintf(
                'Yes. I found %d similar previous %s.',
                $matches->count(),
                $matches->count() === 1
                    ? 'experience'
                    : 'experiences'
            ),
        ];

        foreach (
            $matches
                ->take(5)
                ->values() as $index => $match
        ) {
            $experience =
                $match->experience;

            $lines[] = '';
            $lines[] =
                sprintf(
                    '%d. %s — similarity %d%%',
                    $index + 1,
                    $experience->subject_name
                        ?? $experience->title,
                    $match->score
                );

            if ($match->reasons !== []) {
                $lines[] = 'Why it matches:';

                foreach ($match->reasons as $reason) {
                    $lines[] =
                        '- '.$reason;
                }
            }

            if ($experience->outcome) {
                $lines[] =
                    sprintf(
                        'Previous outcome: %s',
                        $experience->outcome
                    );
            }
        }

        return new BusinessResponse(
            answer: implode(
                PHP_EOL,
                $lines
            ),
            context: $context,
            questions: [
                'What solved this before?',
                'Show me the current investigation.',
            ]
        );
    }

    private function whatSolvedBefore(
        $matches,
        ConversationContext $context
    ): BusinessResponse {
        $match =
            $matches->first();

        $experience =
            $match->experience;

        $lines = [
            sprintf(
                'The closest previous experience is %s at %d%% similarity.',
                $experience->subject_name
                    ?? $experience->title,
                $match->score
            ),
        ];

        if ($experience->outcome) {
            $lines[] = '';
            $lines[] =
                'What resolved it: '
                .$experience->outcome;
        }

        $lessons =
            collect(
                $experience->lessons
                ?? []
            )
                ->filter(
                    fn ($value) => is_string($value)
                        && trim($value) !== ''
                )
                ->values();

        if ($lessons->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'What we learned:';

            foreach ($lessons as $lesson) {
                $lines[] =
                    '- '.$lesson;
            }
        }

        $lines[] = '';
        $lines[] =
            'This is historical experience, not proof that the same explanation applies to the current investigation.';

        return new BusinessResponse(
            answer: implode(
                PHP_EOL,
                $lines
            ),
            context: $context,
            questions: [
                'Show me similar cases.',
                'What evidence is still missing?',
            ]
        );
    }

    private function resolveInvestigation(
        ConversationContext $context
    ): ?InvestigationCase {
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

        if (
            $context->subjectType
            && $context->subjectId
        ) {
            return InvestigationCase::query()
                ->where(
                    'subject_type',
                    $context->subjectType
                )
                ->where(
                    'subject_id',
                    $context->subjectId
                )
                ->latest(
                    'opened_at'
                )
                ->first();
        }

        return null;
    }

    private function intent(
        string $question
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
                'what solved this before'
            )
            || str_contains(
                $question,
                'what solved it before'
            )
            || str_contains(
                $question,
                'what happened last time'
            )
        ) {
            return 'what_solved_before';
        }

        if (
            str_contains(
                $question,
                'have we seen this before'
            )
            || str_contains(
                $question,
                'seen this before'
            )
            || str_contains(
                $question,
                'similar cases'
            )
            || str_contains(
                $question,
                'similar experience'
            )
        ) {
            return 'show_similar';
        }

        return null;
    }
}
