<?php

namespace Tests\Feature;

use App\Domains\Imports\Detection\StatementProviderDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementProviderDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_amex_csv_is_detected(): void
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'money-imp-amex-'
        ).'.csv';

        file_put_contents(
            $path,
            implode("\n", [
                'Date,Description,Amount,Reference',
                '01/08/2026,OPENAI *CHATGPT,-20.00,ABC123',
            ])
        );

        try {
            $provider = app(
                StatementProviderDetector::class
            )->detect(
                $path,
                'csv'
            );

            $this->assertSame(
                'amex_csv',
                $provider
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_starling_csv_is_detected(): void
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'money-imp-starling-'
        ).'.csv';

        file_put_contents(
            $path,
            implode("\n", [
                'Date,Counter Party,Reference,Type,Amount (GBP),Balance (GBP),Spending Category,Notes',
                '01/08/2026,OpenAI,OPENAI,ONLINE PAYMENT,-81.18,1000.00,ADMIN,',
            ])
        );

        try {
            $provider = app(
                StatementProviderDetector::class
            )->detect(
                $path,
                'csv'
            );

            $this->assertSame(
                'starling_csv',
                $provider
            );
        } finally {
            @unlink($path);
        }
    }
}
