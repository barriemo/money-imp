<?php

namespace App\Domains\Imports\Parsers\Pdf;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\DTOs\ImportedTransaction;
use Carbon\CarbonImmutable;
use Generator;

class RbsPdfParser extends AbstractPdfStatementParser implements StatementParser
{
    private const TRANSACTION_TYPES = [
        'Mobile/Digital Banking',
        'Debit Card Transaction',
        'Automated Pay In',
        'Direct Debit',
        'Charges',
    ];

    public function provider(): string
    {
        return 'rbs_pdf';
    }

    public function parse(string $path): Generator
    {
        $text = $this->text($path);

        $year = $this->statementYear($text);

        foreach ($this->transactionLines($text) as $line) {
            $parsed = $this->parseTransactionLine(
                $line,
                $year
            );

            if (! $parsed) {
                continue;
            }

            yield $parsed;
        }
    }

    public function parseTransactionLine(
        string $line,
        int $year
    ): ?ImportedTransaction {
        if (
            ! preg_match(
                '/^(?<date>\d{1,2}\s+[A-Z][a-z]{2})\s+(?<body>.+?)\s+(?<amount>-?£[\d,]+\.\d{2})$/',
                trim($line),
                $matches
            )
        ) {
            return null;
        }

        [
            'description' => $description,
            'type' => $type,
        ] = $this->splitDescriptionAndType(
            trim($matches['body'])
        );

        $amount = (float) str_replace(
            [
                '£',
                ',',
            ],
            '',
            $matches['amount']
        );

        return new ImportedTransaction(
            date: CarbonImmutable::createFromFormat(
                'j M Y',
                $matches['date'].' '.$year
            ),
            description: $description,
            amount: $amount,
            merchant: $description,
            reference: null,
            raw: [
                'source_line' => trim($line),
                'transaction_type' => $type,
                'direction' => $amount < 0
                    ? 'money_out'
                    : 'money_in',
            ],
        );
    }

    private function transactionLines(
        string $text
    ): array {
        return array_values(
            array_filter(
                array_map(
                    'trim',
                    preg_split(
                        '/\R/',
                        $text
                    ) ?: []
                ),
                fn (string $line) => (bool) preg_match(
                    '/^\d{1,2}\s+[A-Z][a-z]{2}\s+.+\s+-?£[\d,]+\.\d{2}$/',
                    $line
                )
            )
        );
    }

    private function splitDescriptionAndType(
        string $body
    ): array {
        foreach (self::TRANSACTION_TYPES as $type) {
            if (
                preg_match(
                    '/^(?<description>.+?)\s+'
                    .preg_quote($type, '/')
                    .'$/i',
                    $body,
                    $matches
                )
            ) {
                return [
                    'description' => trim($matches['description']),
                    'type' => $type,
                ];
            }
        }

        return [
            'description' => trim($body),
            'type' => null,
        ];
    }

    private function statementYear(
        string $text
    ): int {
        if (
            preg_match(
                '/To\s+(\d{2}\/\d{2}\/(\d{4}))/i',
                $text,
                $matches
            )
        ) {
            return (int) $matches[2];
        }

        return (int) now()->year;
    }
}
