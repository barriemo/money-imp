<?php

namespace App\Providers;

use App\Domains\BusinessBrain\Attention\AttentionSignalCollector;
use App\Domains\BusinessBrain\Attention\Builders\VATAttentionProvider;
use App\Domains\BusinessBrain\Attention\Providers\AllocationAttentionProvider;
use App\Domains\BusinessBrain\Attention\Providers\RecoveryAttentionProvider;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshotRepository;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshotRepository;
use App\Domains\Evidence\EvidenceRepository;
use App\Domains\EvidenceAcquisition\EvidenceAcquisitionEngine;
use App\Domains\EvidenceAcquisition\Providers\FinancialEvidenceProvider;
use App\Domains\EvidenceAcquisition\Providers\InfrastructureEvidenceProvider;
use App\Domains\EvidenceAcquisition\Ranking\EvidenceQueueBuilder;
use App\Domains\ResourceIntelligence\Allocation\AllocationVarianceRepository;
use App\Domains\ResourceIntelligence\Attribution\ResourceContributionRepository;
use App\Domains\VATIntelligence\VATPositionRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ResourceContributionRepository::class,
            function () {
                return new ResourceContributionRepository;
            }
        );

        $this->app->singleton(
            AllocationVarianceRepository::class,
            function () {
                return new AllocationVarianceRepository;
            }
        );

        $this->app->singleton(
            VATPositionRepository::class,
            function () {
                return new VATPositionRepository;
            }
        );

        $this->app->singleton(
            MorningBriefSnapshotRepository::class,
            function () {
                return new MorningBriefSnapshotRepository;
            }
        );

        $this->app->singleton(
            BusinessObservationSnapshotRepository::class,
            function () {
                return new BusinessObservationSnapshotRepository;
            }
        );

        $this->app->singleton(
            AttentionSignalCollector::class,
            function ($app): AttentionSignalCollector {
                return new AttentionSignalCollector([
                    $app->make(
                        RecoveryAttentionProvider::class
                    ),

                    $app->make(
                        AllocationAttentionProvider::class
                    ),

                    $app->make(
                        VATAttentionProvider::class
                    ),
                ]);
            }
        );

        $this->app->singleton(
            EvidenceRepository::class
        );

        $this->app->singleton(
            EvidenceAcquisitionEngine::class,
            function ($app): EvidenceAcquisitionEngine {
                return new EvidenceAcquisitionEngine(
                    providers: [
                        $app->make(
                            FinancialEvidenceProvider::class
                        ),

                        $app->make(
                            InfrastructureEvidenceProvider::class
                        ),
                    ],

                    queueBuilder: $app->make(
                        EvidenceQueueBuilder::class
                    )
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
