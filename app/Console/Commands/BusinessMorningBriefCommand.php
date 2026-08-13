<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
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

        MorningBriefConsolePresenter $presenter
    ): int {
        $brief =
            $service->build(
                new AttentionContext(
                    client: 'Business'
                )
            );

        $this->line(
            $presenter->present(
                $brief
            )
        );

        return self::SUCCESS;
    }
}
