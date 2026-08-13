<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\MorningBrief\MorningBrief;
use App\Domains\BusinessBrain\MorningBrief\Presenters\MorningBriefConsolePresenter;
use Tests\TestCase;

class MorningBriefConsolePresenterTest extends TestCase
{
    public function test_morning_brief_is_presented_for_console_users(): void
    {
        $brief =
            new MorningBrief(
                collect([
                    (object) [
                        'type' => 'vat_exposure',

                        'value' => 30000.0,

                        'reason' => 'VAT liability requires cash planning.',
                    ],
                ])
            );

        $output =
            app(
                MorningBriefConsolePresenter::class
            )->present(
                $brief
            );

        $this->assertStringContainsString(
            'MORNING BUSINESS BRIEF',
            $output
        );

        $this->assertStringContainsString(
            'VAT EXPOSURE',
            $output
        );

        $this->assertStringContainsString(
            '30,000',
            $output
        );
    }
}
