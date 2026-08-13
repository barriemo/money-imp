<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefBusinessResolver;
use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefContextBuilder;
use App\Domains\BusinessBrain\MorningBrief\Presenters\MorningBriefConsolePresenter;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:morning-brief')]
#[Description('Generate the business morning attention brief')]
class BusinessMorningBriefCommand extends Command
{
    public function handle(
        MorningBriefService $service,

        MorningBriefBusinessResolver $resolver,

        MorningBriefContextBuilder $contextBuilder,

        MorningBriefConsolePresenter $presenter
    ): int {
        $clients =
            $resolver->resolve();

        foreach ($clients as $client) {
            $brief =
                $service->build(
                    $contextBuilder->build(
                        $client
                    )
                );

            $this->line(
                $presenter->present(
                    $brief
                )
            );
        }

        return self::SUCCESS;
    }
}
