<?php

namespace App\Domains\Suppliers\Assets;

use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupplierAssetDetector
{
    public function detect(
        BankTransaction $transaction
    ): Collection {
        $text = implode(' ', [
            $transaction->description ?? '',
            $transaction->reference ?? '',
            data_get(
                $transaction->metadata,
                'merchant',
                ''
            ),
            data_get(
                $transaction->raw_payload,
                'merchant',
                ''
            ),
        ]);

        return collect([
            ...$this->domains($text),
            ...$this->servers($text),
        ])->unique(
            fn (array $asset) => $asset['type'].'|'.$asset['key']
        )->values();
    }

    private function domains(
        string $text
    ): array {
        preg_match_all(
            '/\b(?:[a-z0-9-]+\.)+(?:co\.uk|org\.uk|me\.uk|com|net|org|io|ai|uk)\b/i',
            $text,
            $matches
        );

        return collect(
            $matches[0] ?? []
        )
            ->map(
                function (string $domain): array {
                    $domain = Str::lower(
                        trim($domain, " .,\t\n\r\0\x0B")
                    );

                    return [
                        'type' => 'domain',
                        'key' => $domain,
                        'name' => $domain,
                        'confidence' => 100,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function servers(
        string $text
    ): array {
        preg_match_all(
            '/\b(?:vps|server|hosting)[\s\-:#]*([a-z0-9\-]{3,})\b/i',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        return collect($matches)
            ->map(
                function (array $match): array {
                    $value = Str::lower(
                        trim($match[0])
                    );

                    return [
                        'type' => 'hosting',
                        'key' => Str::slug($value),
                        'name' => $value,
                        'confidence' => 75,
                    ];
                }
            )
            ->values()
            ->all();
    }
}
