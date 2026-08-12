<?php

namespace App\Providers;

use App\Domains\EvidenceAcquisition\EvidenceAcquisitionEngine;
use App\Domains\EvidenceAcquisition\Providers\FinancialEvidenceProvider;
use App\Domains\EvidenceAcquisition\Providers\InfrastructureEvidenceProvider;
use App\Domains\EvidenceAcquisition\Ranking\EvidenceQueueBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
