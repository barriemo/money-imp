<?php

namespace App\Providers;

use App\Domains\EvidenceAcquisition\EvidenceAcquisitionEngine;
use App\Domains\EvidenceAcquisition\Providers\FinancialEvidenceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            EvidenceAcquisitionEngine::class,
            function ($app): EvidenceAcquisitionEngine {
                return new EvidenceAcquisitionEngine(
                    [
                        $app->make(
                            FinancialEvidenceProvider::class
                        ),
                    ]
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}
