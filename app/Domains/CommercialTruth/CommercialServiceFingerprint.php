<?php

namespace App\Domains\CommercialTruth;

use Illuminate\Support\Str;

class CommercialServiceFingerprint
{
    public function fingerprint(
        string $description
    ): array {
        $normalised =
            $this->normalise(
                $description
            );

        $serviceType =
            $this->serviceType(
                $normalised
            );

        $serviceHint =
            $this->serviceHint(
                $description
            );

        return [
            'service_type' => $serviceType,

            'service_hint' => $serviceHint,

            'fingerprint' => hash(
                'sha256',
                implode('|', [
                    $serviceType,
                    strtolower(
                        $serviceHint
                        ?? ''
                    ),
                ])
            ),
        ];
    }

    private function normalise(
        string $description
    ): string {
        return Str::of(
            $description
        )
            ->lower()
            ->replace([
                'monthly',
                'annual',
                'annually',
                'yearly',
                'per month',
                'per annum',
                'renewal',
            ], '')
            ->replaceMatches(
                '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)(?:uary|ruary|ch|il|e|y|ust|tember|ober|ember)?\b/i',
                ''
            )
            ->replaceMatches(
                '/\b20\d{2}\b/',
                ''
            )
            ->replaceMatches(
                '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/',
                ''
            )
            ->replaceMatches(
                '/\s+/',
                ' '
            )
            ->trim()
            ->toString();
    }

    private function serviceType(
        string $description
    ): string {
        return match (true) {
            Str::contains(
                $description,
                [
                    'hosting',
                    'server hosting',
                    'website hosting',
                ]
            ) => 'hosting',

            Str::contains(
                $description,
                [
                    'microsoft 365',
                    'office 365',
                    'm365',
                    'exchange online',
                ]
            ) => 'microsoft365',

            Str::contains(
                $description,
                [
                    'seo',
                    'search engine optimisation',
                    'search engine optimization',
                ]
            ) => 'seo',

            Str::contains(
                $description,
                [
                    'ppc',
                    'google ads',
                    'paid search',
                ]
            ) => 'ppc',

            Str::contains(
                $description,
                [
                    'support',
                    'maintenance',
                ]
            ) => 'support',

            default => 'other',
        };
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

        $hint =
            trim(
                Str::afterLast(
                    $description,
                    '-'
                )
            );

        $hint =
            Str::of(
                $hint
            )
                ->replaceMatches(
                    '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)(?:uary|ruary|ch|il|e|y|ust|tember|ober|ember)?\b/i',
                    ''
                )
                ->replaceMatches(
                    '/\b20\d{2}\b/',
                    ''
                )
                ->replaceMatches(
                    '/\s+/',
                    ' '
                )
                ->trim()
                ->toString();

        return $hint !== ''
            ? $hint
            : null;
    }
}
