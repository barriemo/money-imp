<?php

namespace App\Domains\BusinessBrain\Signals;

final class CeoSignalDomainResolver
{
    public function resolve(
        string $input
    ): ?string {
        $input =
            strtolower(
                trim(
                    $input
                )
            );

        /*
         * Deliberately conservative.
         *
         * "Cash feels bad" at business level is not enough
         * to assume a client-ledger investigation.
         *
         * These terms indicate invoice/payment/debtor
         * reconciliation intent.
         */
        foreach (
            [
                '/\binvo[a-z0-9]*\b/i',
                '/\bpaid\b/i',
                '/\bpay(?:ment|ments|emnt|emnts)\b/i',
                '/\bowe(?:s|d)?\b/i',
                '/\bowing\b/i',
                '/\boutstanding\b/i',
                '/\bdebtor[a-z0-9]*\b/i',
                '/\breceipt[a-z0-9]*\b/i',
                '/\bbank\b/i',
                '/\bledger\b/i',
                '/\breconcil[a-z0-9]*\b/i',
            ] as $pattern
        ) {
            if (
                preg_match(
                    $pattern,
                    $input
                ) === 1
            ) {
                return 'client_ledger';
            }
        }

        return null;
    }
}
