<?php

namespace App\Domains\Imports\Parsers\Csv;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\DTOs\ImportedTransaction;
use Carbon\CarbonImmutable;
use Generator;
use RuntimeException;

class StarlingCsvParser implements StatementParser
{
    public function provider(): string
    {
        return 'starling_csv';
    }

    public function parse(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException(
                'Could not open Starling CSV.'
            );
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException(
                'Starling CSV has no header row.'
            );
        }

        $headers = array_map(
            fn ($header) => trim((string) $header),
            $headers
        );

        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) !== count($headers)) {
                continue;
            }

            $row = array_combine(
                $headers,
                $values
            );

            if (! is_array($row)) {
                continue;
            }

            yield new ImportedTransaction(
                date: CarbonImmutable::parse(
                    $row['Date']
                ),
                description: trim(
                    (string) (
                        $row['Counter Party']
                        ?? $row['Reference']
                        ?? ''
                    )
                ),
                amount: (float) str_replace(
                    ',',
                    '',
                    (string) ($row['Amount (GBP)'] ?? 0)
                ),
                reference: trim(
                    (string) ($row['Reference'] ?? '')
                ) ?: null,
                raw: $row,
            );
        }

        fclose($handle);
    }
}
