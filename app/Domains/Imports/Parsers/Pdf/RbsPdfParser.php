<?php

namespace App\Domains\Imports\Parsers\Pdf;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\DTOs\ImportedTransaction;
use Carbon\CarbonImmutable;
use Generator;

class RbsPdfParser extends AbstractPdfStatementParser implements StatementParser
{
    public function provider(): string
    {
        return 'rbs_pdf';
    }

    public function parse(string $path): Generator
    {
        $year = $this->statementYear(
            $this->text($path)
        );

        foreach ($this->lines($path) as $line) {
            if (
                ! preg_match(
                    '/^(\d{1,2}\s+[A-Z][a-z]{2})\s+(.+?)\s+(-?£[\d,]+\.\d{2})$/',
                    $line,
                    $matches
                )
            ) {
                continue;
            }

            $amount = (float) str_replace(
                ['£', ','],
                '',
                $matches[3]
            );

            yield new ImportedTransaction(
                date: CarbonImmutable::createFromFormat(
                    'j M Y',
                    $matches[1].' '.$year
                ),
                description: trim($matches[2]),
                amount: $amount,
                reference: null,
                raw: [
                    'source_line' => $line,
                ],
            );
        }
    }

    private function statementYear(string $text): int
    {
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
