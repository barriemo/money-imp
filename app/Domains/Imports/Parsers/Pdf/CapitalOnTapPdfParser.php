<?php

namespace App\Domains\Imports\Parsers\Pdf;

use App\Domains\Imports\Contracts\StatementParser;
use App\Domains\Imports\DTOs\ImportedTransaction;
use Carbon\CarbonImmutable;
use Generator;

class CapitalOnTapPdfParser extends AbstractPdfStatementParser implements StatementParser
{
    public function provider(): string
    {
        return 'capital_on_tap_pdf';
    }

    public function parse(string $path): Generator
    {
        foreach ($this->lines($path) as $line) {
            $transaction = $this->parseTransactionLine(
                $line
            );

            if (! $transaction) {
                continue;
            }

            yield $transaction;
        }
    }

    public function parseTransactionLine(
        string $line
    ): ?ImportedTransaction {
        $line = preg_replace(
            '/© Copyright.*$/u',
            '',
            $line
        ) ?? $line;

        $line = trim($line);

        /*
         * Normal card transaction:
         *
         * 15/07/2026 15/07/2026 Card *9241
         * OPENAI *CHATGPT SUBSCR - +14158799686
         * 17.99 31762.85
         */
        if (
            preg_match(
                '/^(?<authorised>\d{2}\/\d{2}\/\d{4})\s+'
                .'(?<cleared>\d{2}\/\d{2}\/\d{4})\s+'
                .'(?<type>Card)\s+'
                .'(?<card>\*\d{4})\s+'
                .'(?<description>.+?)\s+'
                .'(?<amount>-?[\d,]+\.\d{2})\s+'
                .'(?<balance>[\d,]+\.\d{2})(?:\s+©.*)?$/',
                $line,
                $matches
            )
        ) {
            return $this->transaction(
                $matches['authorised'],
                $matches['cleared'],
                $matches['type'],
                $matches['card'],
                trim($matches['description']),
                (float) str_replace(
                    ',',
                    '',
                    $matches['amount']
                ),
                (float) str_replace(
                    ',',
                    '',
                    $matches['balance']
                ),
                $line
            );
        }

        /*
         * Non-card statement transactions such as:
         *
         * - 26/07/2026 Interest -
         * Interest Charge (...) 780.35 34353.76
         *
         * - 11/03/2026 - -
         * P2112H69AP -5000.00 8127.31
         */
        if (
            preg_match(
                '/^(?<authorised>-|\d{2}\/\d{2}\/\d{4})\s+'
                .'(?<cleared>\d{2}\/\d{2}\/\d{4})\s+'
                .'(?<type>\S+)\s+'
                .'(?<card>-|\*\d{4})\s+'
                .'(?<description>.+?)\s+'
                .'(?<amount>-?[\d,]+\.\d{2})\s+'
                .'(?<balance>[\d,]+\.\d{2})(?:\s+©.*)?$/',
                $line,
                $matches
            )
        ) {
            $description = trim(
                $matches['description']
            );

            if (
                in_array(
                    $description,
                    [
                        'Opening Balance -',
                        'Closing Balance -',
                    ],
                    true
                )
            ) {
                return null;
            }

            return $this->transaction(
                $matches['authorised'] === '-'
                    ? $matches['cleared']
                    : $matches['authorised'],
                $matches['cleared'],
                $matches['type'],
                $matches['card'] === '-'
                    ? null
                    : $matches['card'],
                $description,
                (float) str_replace(
                    ',',
                    '',
                    $matches['amount']
                ),
                (float) str_replace(
                    ',',
                    '',
                    $matches['balance']
                ),
                $line
            );
        }

        return null;
    }

    private function transaction(
        string $authorisedDate,
        string $clearedDate,
        string $type,
        ?string $card,
        string $description,
        float $statementAmount,
        float $balance,
        string $sourceLine
    ): ImportedTransaction {
        /*
         * Capital on Tap statement convention:
         *
         * positive = spend / interest
         * negative = repayment / refund
         *
         * Money Imp convention:
         *
         * negative = money out / expense
         * positive = money in / credit
         */
        $amount = $statementAmount * -1;

        return new ImportedTransaction(
            date: CarbonImmutable::createFromFormat(
                'd/m/Y',
                $clearedDate
            ),
            amount: $amount,
            description: $description,
            merchant: $this->merchant(
                $description
            ),
            reference: null,
            raw: [
                'authorised_date' => $authorisedDate,

                'cleared_date' => $clearedDate,

                'transaction_type' => $type,

                'card' => $card,

                'statement_amount' => $statementAmount,

                'running_balance' => $balance,

                'direction' => $amount < 0
                        ? 'money_out'
                        : 'money_in',

                'source_line' => $sourceLine,
            ],
        );
    }

    private function merchant(
        string $description
    ): string {
        $merchant = preg_replace(
            '/\s+-\s+.+$/',
            '',
            $description
        );

        return trim(
            $merchant ?: $description
        );
    }
}
