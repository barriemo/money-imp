<?php

namespace App\Domains\MoneyOut\Services;

class MerchantNormaliser
{
    public function normalise(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? '';

        $value = preg_replace(
            '/\s+/',
            ' ',
            $value
        ) ?? '';

        return trim($value);
    }
}
