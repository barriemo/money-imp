<?php

namespace App\Domains\Suppliers\Documents\Parsers;

use Illuminate\Support\Str;

class TwentyIInvoiceParser implements SupplierDocumentParser
{
    public function supports(
        string $supplier
    ): bool {
        return strtolower($supplier) === '20i';
    }

    public function parse(
        string $text
    ): array {
        $assets = [];

        foreach (
            preg_split('/\R/', $text) as $line
        ) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (
                preg_match(
                    '/20iCloud Micro Server \(([^)]+)\).*£([\d.]+)/i',
                    $line,
                    $match
                )
            ) {
                $assets[] = [
                    'type' => 'hosting_server',
                    'key' => Str::lower($match[1]),
                    'name' => $match[1],
                    'cost' => (float) $match[2],
                    'confidence' => 100,
                ];

                continue;
            }

            if (
                preg_match(
                    '/8 Core Managed VPS \(([^)]+)\).*£([\d.]+)/i',
                    $line,
                    $match
                )
            ) {
                $assets[] = [
                    'type' => 'hosting_server',
                    'key' => Str::slug($match[1]),
                    'name' => $match[1],
                    'cost' => (float) $match[2],
                    'confidence' => 100,
                ];

                continue;
            }

            if (
                preg_match(
                    '/Cloud Server Timeline Storage upgrade.*£([\d.]+)/i',
                    $line,
                    $match
                )
            ) {
                $assets[] = [
                    'type' => 'storage',
                    'key' => 'timeline-storage',
                    'name' => 'Cloud Server Timeline Storage',
                    'cost' => (float) $match[1],
                    'confidence' => 100,
                ];
            }
        }

        return $assets;
    }
}
