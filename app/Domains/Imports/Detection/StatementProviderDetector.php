<?php

namespace App\Domains\Imports\Detection;

use RuntimeException;
use Smalot\PdfParser\Parser;

class StatementProviderDetector
{
    public function detect(
        string $path,
        string $extension
    ): string {
        $extension = strtolower($extension);

        if ($extension === 'pdf') {
            return $this->detectPdf($path);
        }

        if (
            in_array(
                $extension,
                ['csv', 'txt'],
                true
            )
        ) {
            return $this->detectCsv($path);
        }

        throw new RuntimeException(
            'Unsupported statement file type.'
        );
    }

    private function detectPdf(
        string $path
    ): string {
        $text = strtolower(
            (new Parser)
                ->parseFile($path)
                ->getText()
        );

        /*
         * Detect RBS from the transaction grammar rather than
         * relying on PDF branding/header extraction.
         *
         * Real RBS exports contain lines such as:
         *
         * 10 Aug BT GROUP PLC Direct Debit -£59.06
         * 10 Aug GOODCALL Automated Pay In £90.00
         */
        $rbsTransactionMatches = preg_match_all(
            '/^\d{1,2}\s+[a-z]{3}\s+.+?\s+'
            .'(?:direct debit|automated pay in|'
            .'mobile\/digital banking|charges)'
            .'\s+-?£[\d,]+\.\d{2}$/mi',
            $text
        );

        if (
            is_int($rbsTransactionMatches)
            && $rbsTransactionMatches >= 3
        ) {
            return 'rbs_pdf';
        }

        /*
         * Capital on Tap must use statement-level identity.
         * The phrase "Capital on Tap" alone can simply be a
         * transaction appearing inside an RBS statement.
         */
        $looksLikeCapitalOnTap =
            str_contains(
                $text,
                'new wave capital'
            )
            || (
                str_contains(
                    $text,
                    'capital on tap'
                )
                && str_contains(
                    $text,
                    'opening balance'
                )
                && str_contains(
                    $text,
                    'closing balance'
                )
                && str_contains(
                    $text,
                    'credit limit'
                )
            );

        if ($looksLikeCapitalOnTap) {
            return 'capital_on_tap_pdf';
        }

        if (
            str_contains(
                $text,
                'american express'
            )
            && str_contains(
                $text,
                'statement of account'
            )
        ) {
            return 'amex_pdf';
        }

        throw new RuntimeException(
            'Money Imp could not identify this PDF statement.'
        );
    }

    private function detectCsv(
        string $path
    ): string {
        $handle = fopen(
            $path,
            'rb'
        );

        if (! $handle) {
            throw new RuntimeException(
                'Could not open CSV statement.'
            );
        }

        $header = fgetcsv(
            $handle,
            null,
            ',',
            '"',
            ''
        ) ?: [];

        fclose($handle);

        $normalised = array_map(
            fn ($value) => strtolower(
                trim(
                    (string) $value
                )
            ),
            $header
        );

        if (
            in_array(
                'counter party',
                $normalised,
                true
            )
            && in_array(
                'amount (gbp)',
                $normalised,
                true
            )
        ) {
            return 'starling_csv';
        }

        if (
            in_array(
                'description',
                $normalised,
                true
            )
            && in_array(
                'amount',
                $normalised,
                true
            )
        ) {
            return 'amex_csv';
        }

        return 'generic_csv';
    }
}
