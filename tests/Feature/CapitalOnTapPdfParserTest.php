<?php

namespace Tests\Feature;

use App\Domains\Imports\Parsers\Pdf\CapitalOnTapPdfParser;
use Tests\TestCase;

class CapitalOnTapPdfParserTest extends TestCase
{
    public function test_card_spend_becomes_money_out(): void
    {
        $transaction = app(
            CapitalOnTapPdfParser::class
        )->parseTransactionLine(
            '15/07/2026 15/07/2026 Card *9241 OPENAI *CHATGPT SUBSCR - +14158799686 17.99 31762.85'
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            '2026-07-15',
            $transaction->date->toDateString()
        );

        $this->assertSame(
            -17.99,
            $transaction->amount
        );

        $this->assertSame(
            'OPENAI *CHATGPT SUBSCR',
            $transaction->merchant
        );

        $this->assertSame(
            '*9241',
            $transaction->raw['card']
        );

        $this->assertSame(
            'money_out',
            $transaction->raw['direction']
        );
    }

    public function test_refund_becomes_money_in(): void
    {
        $transaction = app(
            CapitalOnTapPdfParser::class
        )->parseTransactionLine(
            '26/07/2026 26/07/2026 Card *9241 CRV*DELIVEROO LONDON G - CRV* -22.08 34340.67'
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            22.08,
            $transaction->amount
        );

        $this->assertSame(
            'money_in',
            $transaction->raw['direction']
        );
    }

    public function test_interest_becomes_money_out(): void
    {
        $transaction = app(
            CapitalOnTapPdfParser::class
        )->parseTransactionLine(
            '- 26/07/2026 Interest - Interest Charge (27/06/2026 - 26/07/2026) 780.35 34353.76'
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            -780.35,
            $transaction->amount
        );

        $this->assertSame(
            'Interest',
            $transaction->raw['transaction_type']
        );
    }

    public function test_opening_balance_is_ignored(): void
    {
        $transaction = app(
            CapitalOnTapPdfParser::class
        )->parseTransactionLine(
            '- 27/06/2026 - - Opening Balance - 30585.51'
        );

        $this->assertNull($transaction);
    }

    public function test_transaction_with_attached_pdf_footer_is_parsed(): void
    {
        $transaction = app(
            CapitalOnTapPdfParser::class
        )->parseTransactionLine(
            '24/07/2026 25/07/2026 Card *9241 '
            .'CRV*DELIVEROO LONDON G - CRV* '
            .'22.41 33561.01© Copyright 2026. '
            .'New Wave Capital Limited'
        );

        $this->assertNotNull($transaction);

        $this->assertSame(
            '2026-07-25',
            $transaction->date->toDateString()
        );

        $this->assertSame(
            -22.41,
            $transaction->amount
        );

        $this->assertSame(
            'CRV*DELIVEROO LONDON G',
            $transaction->merchant
        );
    }
}
