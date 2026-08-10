<?php

namespace Tests\Feature;

use App\Domains\Imports\Parsers\Pdf\RbsPdfParser;
use Tests\TestCase;

class RbsPdfParserTest extends TestCase
{
    public function test_rbs_money_out_line_is_parsed(): void
    {
        $transaction = app(
            RbsPdfParser::class
        )->parseTransactionLine(
            '10 Aug BT GROUP PLC Direct Debit -£59.06',
            2026
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            '2026-08-10',
            $transaction->date->toDateString()
        );

        $this->assertSame(
            'BT GROUP PLC',
            $transaction->description
        );

        $this->assertSame(
            'BT GROUP PLC',
            $transaction->merchant
        );

        $this->assertSame(
            -59.06,
            $transaction->amount
        );

        $this->assertSame(
            'Direct Debit',
            $transaction->raw['transaction_type']
        );

        $this->assertSame(
            'money_out',
            $transaction->raw['direction']
        );
    }

    public function test_rbs_money_in_line_is_parsed(): void
    {
        $transaction = app(
            RbsPdfParser::class
        )->parseTransactionLine(
            '07 Jul WALKER THE JEWELLE Automated Pay In £3,000.00',
            2026
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            'WALKER THE JEWELLE',
            $transaction->description
        );

        $this->assertSame(
            3000.0,
            $transaction->amount
        );

        $this->assertSame(
            'Automated Pay In',
            $transaction->raw['transaction_type']
        );

        $this->assertSame(
            'money_in',
            $transaction->raw['direction']
        );
    }

    public function test_rbs_charge_line_is_parsed(): void
    {
        $transaction = app(
            RbsPdfParser::class
        )->parseTransactionLine(
            '20 Feb 30JAN A/C 00292991 Charges -£26.25',
            2026
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            '30JAN A/C 00292991',
            $transaction->description
        );

        $this->assertSame(
            'Charges',
            $transaction->raw['transaction_type']
        );
    }
}
