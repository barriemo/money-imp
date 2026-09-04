<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPresenter;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReport;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReportService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class BusinessExplainCommandTest extends TestCase
{
    public function test_business_explain_outputs_presented_report(): void
    {
        $report =
            Mockery::mock(
                BusinessStateExplanationReport::class
            );

        $service =
            Mockery::mock(
                BusinessStateExplanationReportService::class
            );

        $presenter =
            Mockery::mock(
                BusinessStateExplanationPresenter::class
            );

        $service
            ->shouldReceive(
                'current'
            )
            ->once()
            ->andReturn(
                $report
            );

        $presenter
            ->shouldReceive(
                'present'
            )
            ->once()
            ->with(
                $report
            )
            ->andReturn(
                'MONEY IMP EXPLANATION REPORT'
            );

        $this->app->instance(
            BusinessStateExplanationReportService::class,
            $service
        );

        $this->app->instance(
            BusinessStateExplanationPresenter::class,
            $presenter
        );

        $exit =
            Artisan::call(
                'business:explain'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'MONEY IMP EXPLANATION REPORT',
            $output
        );
    }
}
