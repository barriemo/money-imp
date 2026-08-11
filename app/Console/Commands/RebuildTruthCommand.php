<?php

namespace App\Console\Commands;

use App\Domains\CheerfulCharlie\Daily\CharlieDailyService;
use App\Domains\RevenueTruth\RevenueRecommendationEngine;
use Illuminate\Console\Command;

class RebuildTruthCommand extends Command
{
    protected $signature = 'money:rebuild-truth';

    protected $description =
        'Rebuild derived business truth and recommendation projections.';

    public function __construct(
        private RevenueRecommendationEngine $revenueRecommendations,
        private CharlieDailyService $charlieDaily
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();

        $this->info(
            'Rebuilding Money Imp truth...'
        );

        $this->newLine();

        $this->line(
            'Revenue Truth'
        );

        $revenue =
            $this->revenueRecommendations
                ->recommendations();

        $this->line(
            '  Recommendations: '
            .$revenue->count()
        );

        $this->newLine();

        $this->line(
            'Charlie Daily'
        );

        $daily =
            $this->charlieDaily
                ->build();

        $this->line(
            '  Clients reviewed: '
            .$daily->client_count
        );

        $this->line(
            '  Need attention: '
            .$daily->attention_count
        );

        $this->line(
            '  New findings: '
            .$daily->new_finding_count
        );

        $this->line(
            '  Resolved findings: '
            .$daily->resolved_finding_count
        );

        $this->newLine();

        $this->info(
            'Truth rebuild complete.'
        );

        $this->newLine();

        return self::SUCCESS;
    }
}
