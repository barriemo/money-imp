<?php

namespace Tests\Feature;

use App\Domains\Suppliers\Documents\Parsers\EukhostInvoiceParser;
use App\Domains\Suppliers\Documents\Parsers\TwentyIInvoiceParser;
use Tests\TestCase;

class SupplierDocumentAssetExtractionTest extends TestCase
{
    public function test_20i_invoice_items_are_extracted(): void
    {
        $text = <<<'TEXT'
Item Price
20iCloud Micro Server (vps-17b94a.mvps.stackcp.net) (renewal) £8.99
Cloud Server Timeline Storage upgrade £1.35
8 Core Managed VPS (Imp1) £89.99
TEXT;

        $assets = (
            new TwentyIInvoiceParser
        )->parse($text);

        $this->assertCount(
            3,
            $assets
        );

        $this->assertSame(
            'vps-17b94a.mvps.stackcp.net',
            $assets[0]['key']
        );

        $this->assertSame(
            8.99,
            $assets[0]['cost']
        );

        $this->assertSame(
            'timeline-storage',
            $assets[1]['key']
        );

        $this->assertSame(
            'imp1',
            $assets[2]['key']
        );

        $this->assertSame(
            89.99,
            $assets[2]['cost']
        );
    }

    public function test_eukhost_server_and_addon_are_extracted(): void
    {
        $text = <<<'TEXT'
Pro 2336 6 Core - 5.77.63.221#WK-DS-0517 (17/08/2026 - 16/09/2026)
Memory: 32 GB
£152.96
Addon (5.77.63.221#WK-DS-0517) - cPanel Premier Metal License (100 Accounts)
£38.14
TEXT;

        $assets = (
            new EukhostInvoiceParser
        )->parse($text);

        $this->assertCount(
            2,
            $assets
        );

        $this->assertSame(
            'hosting_server',
            $assets[0]['type']
        );

        $this->assertStringContainsString(
            '5-77-63-221',
            $assets[0]['key']
        );

        $this->assertSame(
            152.96,
            $assets[0]['cost']
        );

        $this->assertSame(
            'hosting_addon',
            $assets[1]['type']
        );

        $this->assertSame(
            38.14,
            $assets[1]['cost']
        );
    }
}
