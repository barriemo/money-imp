<?php

namespace App\Domains\Suppliers\Documents\Parsers;

use Illuminate\Support\Str;

class EukhostInvoiceParser implements SupplierDocumentParser
{
    public function supports(
        string $supplier
    ): bool {
        return strtolower($supplier) === 'eukhost';
    }

    public function parse(
        string $text
    ): array {
        $assets = [];

        $lines = collect(
            preg_split('/\R/', $text)
        )
            ->map(
                fn (string $line) => trim($line)
            )
            ->filter()
            ->values();

        for (
            $index = 0;
            $index < $lines->count();
            $index++
        ) {
            $line = $lines[$index];

            if (
                preg_match(
                    '/Pro 2336 6 Core - ([^\s]+).*$/i',
                    $line,
                    $match
                )
            ) {
                $identity = trim($match[1]);

                $assets[] = [
                    'type' => 'hosting_server',
                    'key' => $this->assetKey(
                        $identity
                    ),
                    'name' => $identity,
                    'cost' => $this->nextPrice(
                        $lines,
                        $index
                    ),
                    'confidence' => 100,
                ];

                continue;
            }

            if (
                preg_match(
                    '/Addon \(([^)]+)\) - (.+)/i',
                    $line,
                    $match
                )
            ) {
                $identity = trim(
                    $match[1]
                );

                $name = trim(
                    $match[2]
                );

                $assets[] = [
                    'type' => 'hosting_addon',

                    'key' => $this->assetKey(
                        $identity
                        .'-'
                        .$name
                    ),

                    'name' => $name,

                    'cost' => $this->nextPrice(
                        $lines,
                        $index
                    ),

                    'confidence' => 100,

                    'parent_key' => $this->assetKey(
                        $identity
                    ),
                ];

                continue;
            }

            if (
                preg_match(
                    '/1 x E5-2630V4 - 10 core \(L\) - ([^.]+)\./i',
                    $line,
                    $match
                )
            ) {
                $identity = trim(
                    $match[1]
                );

                if (
                    strtolower($identity)
                    === 'hostname'
                ) {
                    $identity =
                        'e5-2630v4-10-core-legacy';
                }

                $assets[] = [
                    'type' => 'hosting_server',

                    'key' => $this->assetKey(
                        $identity
                    ),

                    'name' => $identity,

                    'cost' => $this->nextPrice(
                        $lines,
                        $index
                    ),

                    'confidence' => 85,
                ];
            }
        }

        return $assets;
    }

    private function nextPrice(
        $lines,
        int $index
    ): ?float {
        for (
            $offset = 1;
            $offset <= 12;
            $offset++
        ) {
            $candidate =
                $lines->get(
                    $index + $offset
                );

            if ($candidate === null) {
                break;
            }

            if (
                preg_match(
                    '/^£([\d,]+\.\d{2})$/',
                    $candidate,
                    $match
                )
            ) {
                return (float) str_replace(
                    ',',
                    '',
                    $match[1]
                );
            }

            if (
                preg_match(
                    '/^(?:Addon|Pro \d|1 x E5-|Sub Total|Total)/i',
                    $candidate
                )
            ) {
                break;
            }
        }

        return null;
    }

    private function assetKey(
        string $value
    ): string {
        return Str::of($value)
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                '-'
            )
            ->trim('-')
            ->value();
    }
}
