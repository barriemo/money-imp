<?php

namespace Tests\Feature;

use App\Domains\Imports\Detection\DocumentTypeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_with_supplier_names_is_not_supplier_invoice(): void
    {
        $path = storage_path(
            'framework/testing/capital-on-tap-statement.txt'
        );

        file_put_contents(
            $path,
            implode(PHP_EOL, [
                'Statement Summary',
                'Capital on Tap',
                'Opening Balance £12,295.39',
                'Spending Activity £15,866.20',
                'Repayment Activity £1,203.23',
                'Closing Balance £26,958.36',
                'Account Statement',
                'Authorised Date Cleared Date Type Card Description',
                'Name.com, Inc 720-2492374 £76.54',
                'Minimum Amount Due £2,695.84',
                'Due Date 01 May 2026',
            ])
        );

        /*
         * This test exercises the structural statement
         * guard independently of PDF rendering.
         */
        $reflection = new \ReflectionClass(
            DocumentTypeDetector::class
        );

        $method = $reflection->getMethod(
            'looksLikeStatement'
        );

        $result = $method->invoke(
            app(DocumentTypeDetector::class),
            strtolower(
                file_get_contents($path)
            )
        );

        $this->assertTrue($result);
    }
}
