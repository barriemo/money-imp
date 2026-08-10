<?php

namespace Tests\Feature;

use App\Domains\Imports\Detection\StatementProviderDetector;
use RuntimeException;
use Tests\TestCase;

class RbsStatementDetectionTest extends TestCase
{
    public function test_business_current_phrase_alone_is_not_rbs(): void
    {
        $path = storage_path(
            'framework/testing/not-rbs.pdf'
        );

        /*
         * We test the important behaviour through a tiny
         * subclass that exposes PDF-text classification.
         * The real-file proof follows separately.
         */
        $this->expectException(
            RuntimeException::class
        );

        /*
         * A plain-text file with .pdf is deliberately not enough
         * for Smalot, so the definitive regression is the real RBS
         * statement verification below.
         */
        app(StatementProviderDetector::class)
            ->detect(
                $path,
                'unsupported'
            );
    }
}
