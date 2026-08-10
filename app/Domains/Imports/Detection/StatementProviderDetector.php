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

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->detectCsv($path);
        }

        throw new RuntimeException(
            'Unsupported statement file type.'
        );
    }

    private function detectPdf(string $path): string
    {
        $text = strtolower(
            (new Parser)
                ->parseFile($path)
                ->getText()
        );

        if (
            str_contains($text, 'american express')
            && str_contains($text, 'statement of account')
        ) {
            return 'amex_pdf';
        }

        if (
            str_contains($text, 'capital on tap')
            || str_contains($text, 'new wave capital')
        ) {
            return 'capital_on_tap_pdf';
        }

        if (
            str_contains($text, 'royal bank of scotland')
            || str_contains($text, 'business current')
        ) {
            return 'rbs_pdf';
        }

        throw new RuntimeException(
            'Money Imp could not identify this PDF statement.'
        );
    }

    private function detectCsv(string $path): string
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException(
                'Could not open CSV statement.'
            );
        }

        $header = fgetcsv($handle) ?: [];

        fclose($handle);

        $normalised = array_map(
            fn ($value) => strtolower(
                trim((string) $value)
            ),
            $header
        );

        if (
            in_array('counter party', $normalised, true)
            && in_array('amount (gbp)', $normalised, true)
        ) {
            return 'starling_csv';
        }

        if (
            in_array('description', $normalised, true)
            && in_array('amount', $normalised, true)
        ) {
            return 'amex_csv';
        }

        return 'generic_csv';
    }
}
