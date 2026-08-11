<?php

namespace App\Domains\Infrastructure\Attribution;

use Illuminate\Support\Str;

class HostingEvidenceBuilder
{
    public function build(
        object $invoiceItem
    ): ?array {
        $description =
            trim(
                (string)
                ($invoiceItem->description ?? '')
            );

        if (! $this->isHosting(
            $description
        )) {
            return null;
        }

        return [
            'description' => $description,

            'monthly_rate' => round(
                (float)
                ($invoiceItem->unit_price ?? 0),
                2
            ),

            'is_hosting' => true,

            'includes_security' => Str::contains(
                Str::lower(
                    $description
                ),
                'security'
            ),

            'includes_backups' => Str::contains(
                Str::lower(
                    $description
                ),
                'backup'
            ),

            'service_hint' => $this->serviceHint(
                $description
            ),
        ];
    }

    private function isHosting(
        string $description
    ): bool {
        $text =
            Str::lower(
                $description
            );

        return Str::contains(
            $text,
            [
                'monthly hosting',
                'website hosting',
                'managed hosting',
                'hosting',
            ]
        );
    }

    private function serviceHint(
        string $description
    ): ?string {
        if (
            ! str_contains(
                $description,
                '-'
            )
        ) {
            return null;
        }

        return trim(
            Str::afterLast(
                $description,
                '-'
            )
        );
    }
}
