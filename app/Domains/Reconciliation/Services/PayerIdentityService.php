<?php

namespace App\Domains\Reconciliation\Services;

use App\Models\BankTransaction;

class PayerIdentityService
{
    public function forTransaction(BankTransaction $transaction): string
    {
        return $this->normalise(
            (string) $transaction->description
        );
    }

    public function normalise(string $value): string
    {
        $value = preg_replace(
            '/\s+(FP|VIA ONLINE|BACS|BGC|FASTER PAYMENT)\b.*$/i',
            '',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\b\d{2}\/\d{2}\/\d{2}\b.*$/',
            '',
            $value
        ) ?? $value;

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                strtolower(
                    preg_replace(
                        '/[^a-z0-9 ]/i',
                        ' ',
                        $value
                    )
                )
            ) ?? ''
        );
    }
}
