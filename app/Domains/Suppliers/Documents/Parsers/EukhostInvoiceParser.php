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

        foreach (
            preg_split('/\R/', $text) as $line
        ) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

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
                    'cost' => null,
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
                $identity = trim($match[1]);
                $name = trim($match[2]);

                $assets[] = [
                    'type' => 'hosting_addon',
                    'key' => $this->assetKey(
                        $identity.'-'.$name
                    ),
                    'name' => $name,
                    'cost' => null,
                    'confidence' => 95,
                    'parent_key' => Str::slug($identity),
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
                $identity = trim($match[1]);

                $assets[] = [
                    'type' => 'hosting_server',
                    'key' => $this->assetKey(
                        $identity
                    ),
                    'name' => $identity,
                    'cost' => null,
                    'confidence' => 90,
                ];
            }
        }

        return $assets;
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
