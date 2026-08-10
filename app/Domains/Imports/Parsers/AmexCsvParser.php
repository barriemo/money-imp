<?php

namespace App\Domains\Imports\Parsers;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\Contracts\TransactionFileParser;
use App\Domains\Imports\DTOs\ImportedTransaction;
use Carbon\CarbonImmutable;
use RuntimeException;
use SplFileObject;

class AmexCsvParser implements StatementParser, TransactionFileParser
{
    public function provider(): string
    {
        return 'amex_csv';
    }

    public function supports(
        string $provider,
        string $extension
    ): bool {
        return strtolower($provider) === 'amex'
            && strtolower($extension) === 'csv';
    }

    public function parse(string $path): iterable
    {
        $file = new SplFileObject($path);
        $file->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::SKIP_EMPTY
        );

        $headers = null;

        foreach ($file as $row) {
            if (
                ! is_array($row)
                || $row === [null]
            ) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(
                    fn ($value) => trim((string) $value),
                    $row
                );

                continue;
            }

            $values = array_pad(
                $row,
                count($headers),
                null
            );

            $data = array_combine(
                $headers,
                array_slice(
                    $values,
                    0,
                    count($headers)
                )
            );

            if (! is_array($data)) {
                continue;
            }

            $date = $this->value(
                $data,
                ['Date', 'Transaction Date']
            );

            $description = $this->value(
                $data,
                ['Description', 'Merchant']
            );

            $amount = $this->value(
                $data,
                ['Amount']
            );

            if (
                $date === null
                || $description === null
                || $amount === null
            ) {
                continue;
            }

            yield new ImportedTransaction(
                date: $this->date($date),
                amount: $this->amount($amount),
                description: $description,
                merchant: $description,
                reference: $this->value(
                    $data,
                    ['Reference', 'Reference Number']
                ),
                raw: $data,
            );
        }
    }

    private function value(
        array $row,
        array $keys
    ): ?string {
        foreach ($keys as $key) {
            if (
                isset($row[$key])
                && trim((string) $row[$key]) !== ''
            ) {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function date(string $value): CarbonImmutable
    {
        foreach ([
            'd/m/Y',
            'd/m/y',
            'Y-m-d',
        ] as $format) {
            $date = CarbonImmutable::createFromFormat(
                $format,
                $value
            );

            if ($date !== false) {
                return $date;
            }
        }

        throw new RuntimeException(
            'Unable to parse transaction date: '.$value
        );
    }

    private function amount(string $value): float
    {
        $value = str_replace(
            [',', '£'],
            '',
            $value
        );

        return (float) $value;
    }
}
