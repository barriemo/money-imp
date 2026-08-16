<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Conversation\ConversationContextStore;
use App\Domains\BusinessBrain\Insights\BusinessInsight;
use App\Domains\BusinessBrain\Interrogation\BusinessInterrogator;
use App\Domains\BusinessBrain\Interrogation\BusinessQuestion;
use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefBusinessResolver;
use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefContextBuilder;
use App\Domains\BusinessBrain\Query\BusinessBrainQueryService;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:ask {question}')]
#[Description('Ask the business brain a question')]
class BusinessAskCommand extends Command
{
    public function handle(
        BusinessInterrogator $interrogator,

        MorningBriefBusinessResolver $resolver,

        MorningBriefContextBuilder $contextBuilder,

        BusinessBrainQueryService $brain,

        ConversationContextStore $conversation
    ): int {
        $rawQuestion =
            (string) $this->argument(
                'question'
            );

        $context =
            $conversation->current();

        $response =
            $brain->ask(
                $rawQuestion,
                $context
            );

        if ($response !== null) {
            if ($response->context !== null) {
                $conversation->save(
                    $response->context
                );
            }

            $this->presentResponse(
                $response
            );

            return self::SUCCESS;
        }

        $question =
            new BusinessQuestion(
                $this->argument('question')
            );

        $normalised =
            $question->normalised();

        if (
            in_array(
                $normalised,
                [
                    'where are we?',
                    'where are we',
                    'which clients need attention today?',
                    'which clients need attention today',
                    'who needs attention today?',
                    'who needs attention today',
                    'where are we losing money?',
                    'where are we losing money',
                    'where is money leaking?',
                    'where is money leaking',
                    'what don\'t you know yet?',
                    'what don\'t you know yet',
                    'what do you not know yet?',
                    'what do you not know yet',
                    'what are your blind spots?',
                    'what are your blind spots',
                    'what should i do today?',
                    'what should i do today',
                    'what should we do today?',
                    'what should we do today',
                    'what should i do next?',
                    'what should i do next',
                    'what changed?',
                    'what changed',
                    'what changed since yesterday?',
                    'what changed since yesterday',
                    'what happened to our recommendations?',
                    'what happened to our recommendations',
                    'how have our recommendations performed?',
                    'how have our recommendations performed',
                    'what happened to our decisions?',
                    'what happened to our decisions',
                    'what have you learned?',
                    'what have you learned',
                    'what strategies are working?',
                    'what strategies are working',
                    'what is working?',
                    'what is working',
                    'what are we waiting on?',
                    'what are we waiting on',
                    'what am i waiting on?',
                    'what am i waiting on',
                    'what is waiting?',
                    'what is waiting',
                    'what is our cash position?',
                    'what is our cash position',
                    'what\'s our cash position?',
                    'what\'s our cash position',
                    'how much cash do we have?',
                    'how much cash do we have',
                ],
                true
            )
            ||
            str_starts_with(
                $normalised,
                'what do you know about '
            )
            ||
            str_starts_with(
                $normalised,
                'what happened with '
            )
            ||
            str_starts_with(
                $normalised,
                'what happened to '
            )
        ) {
            $this->present(
                $interrogator->ask(
                    $question
                )
            );

            return self::SUCCESS;
        }

        foreach (
            $resolver->resolve() as $client
        ) {
            $this->present(
                $interrogator->ask(
                    $question,

                    $contextBuilder->build(
                        $client
                    )
                )
            );
        }

        return self::SUCCESS;
    }

    private function presentResponse(
        BusinessResponse $response
    ): void {
        if ($response->insight) {
            $this->info(
                $response->insight->headline
            );

            $this->newLine();
        }

        $this->line(
            $response->answer
        );

        if (
            $response->insight
            && $response->insight->metrics !== []
        ) {
            $this->newLine();

            foreach (
                $response->insight->metrics as $key => $value
            ) {
                $this->line(
                    sprintf(
                        '%s: %s',
                        $key,
                        $value
                    )
                );
            }
        }

        if (
            $response->insight
            && $response->insight->risks !== []
        ) {
            $this->newLine();

            $this->warn(
                'Risks'
            );

            foreach (
                $response->insight->risks as $risk
            ) {
                $this->line(
                    '- '.$risk
                );
            }
        }

        if ($response->proposedActions !== []) {
            $this->newLine();

            $this->info(
                'Actions'
            );

            foreach (
                $response->proposedActions as $action
            ) {
                $this->line(
                    '- '.$action
                );
            }
        }

        if ($response->questions !== []) {
            $this->newLine();

            $this->info(
                'You can ask'
            );

            foreach (
                $response->questions as $question
            ) {
                $this->line(
                    '- '.$question
                );
            }
        }

        if ($response->insight) {
            $this->newLine();

            $this->line(
                'confidence: '.$response->insight->confidence.'%'
            );
        }
    }

    private function presentInsight(
        BusinessInsight $insight
    ): void {
        $this->info(
            $insight->headline
        );

        $this->newLine();

        $this->line(
            $insight->summary
        );

        if ($insight->metrics !== []) {
            $this->newLine();

            foreach (
                $insight->metrics as $key => $value
            ) {
                $this->line(
                    sprintf(
                        '%s: %s',
                        $key,
                        $value
                    )
                );
            }
        }

        if ($insight->risks !== []) {
            $this->newLine();

            $this->warn(
                'Risks'
            );

            foreach ($insight->risks as $risk) {
                $this->line(
                    '- '.$risk
                );
            }
        }

        if ($insight->actions !== []) {
            $this->newLine();

            $this->info(
                'Actions'
            );

            foreach ($insight->actions as $action) {
                $this->line(
                    '- '.$action
                );
            }
        }

        $this->newLine();

        $this->line(
            'confidence: '.$insight->confidence.'%'
        );
    }

    private function present(
        $answer
    ): void {
        $this->line(
            $answer->answer
        );

        $this->newLine();

        foreach (
            $answer->facts as $key => $value
        ) {
            $this->line(
                sprintf(
                    '%s: %s',
                    $key,
                    is_numeric($value)
                        ? number_format($value)
                        : $value
                )
            );
        }
    }
}
