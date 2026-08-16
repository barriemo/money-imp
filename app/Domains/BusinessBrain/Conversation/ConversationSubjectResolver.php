<?php

namespace App\Domains\BusinessBrain\Conversation;

class ConversationSubjectResolver
{
    public function resolve(
        string $question,
        ConversationContext $context
    ): ?array {
        if (
            $context->issue !== 'client_ledger_anomalies'
            || $context->unresolvedQuestions === []
        ) {
            return null;
        }

        $question =
            strtolower(
                trim(
                    $question
                )
            );

        $candidates =
            collect(
                $context->unresolvedQuestions
            );

        $matches =
            $candidates
                ->filter(
                    function (array $candidate) use ($question): bool {
                        $name =
                            strtolower(
                                (string) (
                                    $candidate['client_name']
                                    ?? ''
                                )
                            );

                        if ($name === '') {
                            return false;
                        }

                        if (
                            str_contains(
                                $question,
                                $name
                            )
                        ) {
                            return true;
                        }

                        $words =
                            collect(
                                preg_split(
                                    '/[^a-z0-9]+/i',
                                    $name
                                )
                            )
                                ->filter(
                                    fn ($word) => strlen(
                                        $word
                                    ) >= 4
                                );

                        return $words->contains(
                            fn ($word) => str_contains(
                                $question,
                                strtolower(
                                    $word
                                )
                            )
                        );
                    }
                )
                ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }
}
