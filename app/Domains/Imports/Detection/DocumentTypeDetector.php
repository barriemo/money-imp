<?php

namespace App\Domains\Imports\Detection;

use RuntimeException;
use Smalot\PdfParser\Parser;

class DocumentTypeDetector
{
    public function __construct(
        private StatementProviderDetector $statements
    ) {}

    public function detect(
        string $path,
        string $extension
    ): array {
        $extension = strtolower($extension);

        if ($extension === 'pdf') {
            return $this->detectPdf($path);
        }

        try {
            $provider = $this->statements->detect(
                $path,
                $extension
            );

            return [
                'type' => 'statement',
                'provider' => $provider,
                'supplier' => null,
                'confidence' => 100,
            ];
        } catch (RuntimeException) {
            return $this->unknown();
        }
    }

    private function detectPdf(
        string $path
    ): array {
        $text = strtolower(
            (new Parser)
                ->parseFile($path)
                ->getText()
        );

        /*
         * Strong statement structures must win before
         * supplier/invoice heuristics.
         *
         * A bank or card statement can contain supplier
         * names and invoice-like phrases such as
         * "amount due" and "due date".
         */
        if ($this->looksLikeStatement($text)) {
            try {
                $provider = $this->statements->detect(
                    $path,
                    'pdf'
                );

                return [
                    'type' => 'statement',
                    'provider' => $provider,
                    'supplier' => null,
                    'confidence' => 100,
                ];
            } catch (RuntimeException) {
                //
            }
        }

        /*
         * Important:
         *
         * Identify invoices BEFORE statements.
         *
         * Invoices often contain bank details such as RBS,
         * sort codes and account numbers. Those payment details
         * must never make an invoice look like a bank statement.
         */
        $invoiceScore = $this->invoiceScore(
            $text
        );

        $supplier = $this->supplier(
            $text
        );

        if (
            $supplier !== null
            && $invoiceScore >= 3
        ) {
            return [
                'type' => 'supplier_invoice',
                'provider' => null,
                'supplier' => $supplier,
                'confidence' => min(
                    100,
                    70 + ($invoiceScore * 5)
                ),
            ];
        }

        if ($invoiceScore >= 5) {
            return [
                'type' => 'invoice',
                'provider' => null,
                'supplier' => $supplier,
                'confidence' => min(
                    100,
                    50 + ($invoiceScore * 5)
                ),
            ];
        }

        try {
            $provider = $this->statements->detect(
                $path,
                'pdf'
            );

            return [
                'type' => 'statement',
                'provider' => $provider,
                'supplier' => null,
                'confidence' => 100,
            ];
        } catch (RuntimeException) {
            //
        }

        if ($supplier !== null) {
            return [
                'type' => 'supplier_document',
                'provider' => null,
                'supplier' => $supplier,
                'confidence' => 70,
            ];
        }

        return $this->unknown();
    }

    private function looksLikeStatement(
        string $text
    ): bool {
        $signals = [
            'statement summary',
            'account statement',
            'opening balance',
            'closing balance',
            'authorised date',
            'cleared date',
            'repayment activity',
            'spending activity',
        ];

        $matches = 0;

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                $matches++;
            }
        }

        return $matches >= 3;
    }

    private function invoiceScore(
        string $text
    ): int {
        $signals = [
            'invoice' => 3,
            'invoice number' => 3,
            'invoice no' => 3,
            'invoice date' => 2,
            'amount due' => 2,
            'payment terms' => 2,
            'subtotal' => 2,
            'vat number' => 2,
            'vat registration' => 2,
            'total due' => 2,
            'bill to' => 2,
            'due date' => 1,
        ];

        $score = 0;

        foreach ($signals as $signal => $weight) {
            if (str_contains($text, $signal)) {
                $score += $weight;
            }
        }

        return $score;
    }

    private function supplier(
        string $text
    ): ?string {
        $suppliers = [
            '20i' => [
                '20i limited',
                '20i.com',
                '20i ltd',
            ],

            'name.com' => [
                'name.com',
                'name.com, inc',
            ],

            'godaddy' => [
                'godaddy',
                'go daddy',
            ],

            'eukhost' => [
                'eukhost',
                'eukhost ltd',
            ],

            '123reg' => [
                '123-reg',
                '123 reg',
                '123reg',
            ],
        ];

        foreach ($suppliers as $supplier => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $supplier;
                }
            }
        }

        return null;
    }

    private function unknown(): array
    {
        return [
            'type' => 'unknown',
            'provider' => null,
            'supplier' => null,
            'confidence' => 0,
        ];
    }
}
