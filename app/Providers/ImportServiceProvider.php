<?php

namespace App\Providers;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\Parsers\AmexCsvParser;
use App\Domains\Imports\Parsers\Csv\StarlingCsvParser;
use App\Domains\Imports\Parsers\Pdf\CapitalOnTapPdfParser;
use App\Domains\Imports\Parsers\Pdf\RbsPdfParser;
use App\Domains\Imports\Services\StatementParserRegistry;
use Illuminate\Support\ServiceProvider;

class ImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag(
            [
                AmexCsvParser::class,
                StarlingCsvParser::class,
                CapitalOnTapPdfParser::class,
                RbsPdfParser::class,
            ],
            StatementParser::class
        );

        $this->app->singleton(
            StatementParserRegistry::class,
            fn ($app) => new StatementParserRegistry(
                $app->tagged(
                    StatementParser::class
                )
            )
        );
    }
}
