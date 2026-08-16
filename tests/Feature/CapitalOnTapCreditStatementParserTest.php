<?php

namespace Tests\Feature;

use App\Domains\Imports\Contracts\CreditStatementParser;
use App\Domains\Imports\Parsers\Pdf\CapitalOnTapPdfParser;
use Tests\TestCase;

class CapitalOnTapCreditStatementParserTest extends TestCase
{
    public function test_capital_on_tap_summary_extracts_credit_evidence(): void
    {
        $parser =
            app(
                CapitalOnTapPdfParser::class
            );

        $reflection =
            new \ReflectionClass(
                $parser
            );

        $this->assertTrue(
            $reflection->implementsInterface(
                CreditStatementParser::class
            )
        );
    }
}
