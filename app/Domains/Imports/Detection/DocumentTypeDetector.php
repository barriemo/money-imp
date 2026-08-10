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
        $extension = strtolower(
            $extension
        );

        try {
            $provider = $this->statements
                ->detect(
                    $path,
                    $extension
                );

            return [
                'type' => 'statement',
                'provider' => $provider,
                'supplier' => null,
            ];
        } catch (RuntimeException) {
            //
        }

        if ($extension === 'pdf') {
            return $this->detectPdfDocument(
                $path
            );
        }

        return [
            'type' => 'unknown',
            'provider' => null,
            'supplier' => null,
        ];
    }

    private function detectPdfDocument(
        string $path
    ): array {
        $text = strtolower(
            (new Parser)
                ->parseFile($path)
                ->getText()
        );

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

        foreach (
            $suppliers as $supplier => $needles
        ) {
            foreach ($needles as $needle) {
                if (
                    str_contains(
                        $text,
                        $needle
                    )
                ) {
                    return [
                        'type' => 'supplier_invoice',
                        'provider' => null,
                        'supplier' => $supplier,
                    ];
                }
            }
        }

        return [
            'type' => 'unknown',
            'provider' => null,
            'supplier' => null,
        ];
    }
}
