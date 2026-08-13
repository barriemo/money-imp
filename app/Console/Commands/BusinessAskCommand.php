<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Interrogation\BusinessInterrogator;
use App\Domains\BusinessBrain\Interrogation\BusinessQuestion;
use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefBusinessResolver;
use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefContextBuilder;
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

        MorningBriefContextBuilder $contextBuilder
    ): int {
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
