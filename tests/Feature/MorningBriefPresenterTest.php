<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\BusinessBrain\MorningBrief\MorningBrief;
use App\Domains\BusinessBrain\MorningBrief\Presenters\MorningBriefPresenter;
use Tests\TestCase;

class MorningBriefPresenterTest extends TestCase
{
    public function test_morning_brief_is_presented_for_business_users(): void
    {
        $brief =
            new MorningBrief(
                collect([
                    new AttentionSignal(
                        type: 'vat_exposure',

                        client: 'Walker',

                        priority: 90,

                        value: 30000,

                        reason: 'VAT liability requires cash planning.'
                    ),
                ])
            );

        $result =
            app(
                MorningBriefPresenter::class
            )->present(
                $brief
            );

        $this->assertSame(
            1,
            $result['signal_count']
        );

        $this->assertSame(
            'vat_exposure',
            $result['signals'][0]['type']
        );

        $this->assertSame(
            30000.0,
            $result['signals'][0]['value']
        );
    }
}
