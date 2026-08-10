<?php

namespace App\Providers;

use App\Domains\Imports\Parsers\AmexCsvParser;
use App\Domains\Imports\Services\TransactionImportService;
use Illuminate\Support\ServiceProvider;

class ImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TransactionImportService::class,
            fn () => new TransactionImportService([
                app(AmexCsvParser::class),
            ])
        );
    }
}
