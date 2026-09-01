<?php

namespace App\Domains\CommercialTruth;

use Illuminate\Support\Str;

class CommercialServiceFingerprint
{
    public function fingerprint(
        string $description
    ): array {
        $normalised = $this->normalise(
            $description
        );

        $serviceType = $this->serviceType(
            $normalised
        );

        $rawServiceHint = $this->serviceHint(
            $description
        );

        $serviceHint = $this->displayServiceHint(
            $rawServiceHint
        );

        $identityHint = $this->identityServiceHint(
            $rawServiceHint
        );

        return [
            'service_type' => $serviceType,
            'service_hint' => $serviceHint,
            'commercial_treatment' => $this->commercialTreatment(
                $serviceType
            ),
            'classification_confidence' => $this->confidence(
                $serviceType
            ),
            'fingerprint' => hash(
                'sha256',
                implode('|', [
                    $serviceType,
                    strtolower(
                        $identityHint
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
            /*
             * Detect pass-through spend BEFORE PPC.
             *
             * "PPC - Advertising spend Budget - Google"
             * is commercially different from
             * "PPC Management".
             */
            Str::contains(
                $description,
                [
                    'advertising spend',
                    'advertising budget',
                    'ad spend',
                    'media spend',
                    'media budget',
                ]
            ) => 'media_spend',

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
                    'mso365',
                    'm365',
                    'exchange online',
                ]
            ) => 'microsoft365',

            Str::contains(
                $description,
                [
                    'google workspace',
                    'g suite',
                ]
            ) => 'google_workspace',

            Str::contains(
                $description,
                [
                    'mailchimp',
                    'postmark',
                ]
            ) => 'email_platform',

            Str::contains(
                $description,
                [
                    'nitro cdn',
                    'cdn',
                    'content delivery network',
                ]
            ) => 'cdn',

            Str::contains(
                $description,
                [
                    'domain management',
                    'domain name',
                    'domain registration',
                    'domain renewal',
                    'domain ',
                    'domains ',
                    ' domain',
                ]
            ) => 'domain',

            Str::contains(
                $description,
                [
                    'social media',
                    'social management',
                ]
            ) => 'social_media',

            Str::contains(
                $description,
                [
                    'ppc management',
                    'paid management - ppc',
                    'paid search management',
                    'google ads management',
                ]
            ) => 'ppc_management',

            Str::contains(
                $description,
                [
                    'seo',
                    'search engine optimisation',
                    'search engine optimization',
                ]
            ) => 'seo',

            /*
             * Retainers before generic support.
             */
            Str::contains(
                $description,
                [
                    'retainer',
                    'consultancy',
                ]
            ) => 'retainer',

            Str::contains(
                $description,
                [
                    'web design',
                    'website design',
                    'web development',
                    'website development',
                    'development work',
                    'microsite',
                ]
            ) => 'development',

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

    private function commercialTreatment(
        string $serviceType
    ): string {
        return match ($serviceType) {
            'media_spend' => 'pass_through_candidate',

            'development' => 'project_candidate',

            'microsoft365',
            'google_workspace',
            'email_platform',
            'cdn',
            'domain' => 'managed_service_candidate',

            'hosting',
            'social_media',
            'ppc_management',
            'seo',
            'retainer',
            'support' => 'service_candidate',

            default => 'unknown',
        };
    }

    private function confidence(
        string $serviceType
    ): int {
        return match ($serviceType) {
            'hosting',
            'microsoft365',
            'google_workspace',
            'email_platform',
            'cdn',
            'media_spend' => 95,

            'domain',
            'social_media',
            'ppc_management',
            'seo',
            'retainer' => 90,

            'development',
            'support' => 85,

            default => 0,
        };
    }

    private function displayServiceHint(
        ?string $hint
    ): ?string {
        if ($hint === null) {
            return null;
        }

        if (
            $this->isPureTemporalHint(
                $hint
            )
        ) {
            return null;
        }

        $normalised = $this->stripTrailingPeriod(
            $hint
        );

        return $normalised !== ''
            ? $normalised
            : null;
    }

    private function identityServiceHint(
        ?string $hint
    ): ?string {
        if ($hint === null) {
            return null;
        }

        /*
         * A pure billing-period suffix should join other
         * period-labelled observations, but deliberately not
         * collapse into historic observations which never had
         * a suffix at all.
         */
        if (
            $this->isPureTemporalHint(
                $hint
            )
        ) {
            return '__periodic_suffix__';
        }

        $normalised = $this->stripTrailingPeriod(
            $hint
        );

        return $normalised !== ''
            ? $normalised
            : null;
    }

    private function isPureTemporalHint(
        string $hint
    ): bool {
        $hint = trim(
            $hint
        );

        return preg_match(
            '/^(?:(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s*(?:20)?\d{2}|(?:20)?(?:2\d|3\d))$/i',
            $hint
        ) === 1;
    }

    private function stripTrailingPeriod(
        string $hint
    ): string {
        return Str::of(
            $hint
        )
            /*
             * Ruby Online March26
             * Ruby Online Mar 26
             * Ruby Online March 2026
             */
            ->replaceMatches(
                '/\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s*(?:20)?\d{2}$/i',
                ''
            )
            /*
             * Ruby Online 26
             *
             * Restrict this to plausible abbreviated years so
             * things such as "Server 2" remain meaningful.
             */
            ->replaceMatches(
                '/\s+(?:2\d|3\d)$/',
                ''
            )
            ->replaceMatches(
                '/\s+/',
                ' '
            )
            ->trim()
            ->toString();
    }

    private function serviceHint(
        string $description
    ): ?string {
        /*
         * Purchase-order numbers identify the customer's
         * procurement reference, not the commercial service.
         *
         * Preserve any meaningful text after the PO number
         * (for example "Legacy") as a possible service hint.
         */
        if (
            preg_match(
                '/-\\s*PO[-\\s]*\\d+\\s*$/i',
                $description
            ) === 1
        ) {
            return null;
        }

        if (
            preg_match(
                '/-\\s*PO[-\\s]*\\d+\\s+(.+)$/i',
                $description,
                $purchaseOrderMatch
            ) === 1
        ) {
            $purchaseOrderHint = trim(
                $purchaseOrderMatch[1]
            );

            return $purchaseOrderHint !== ''
                ? $purchaseOrderHint
                : null;
        }

        if (
            ! str_contains(
                $description,
                '-'
            )
        ) {
            return null;
        }

        $hint = trim(
            Str::afterLast(
                $description,
                '-'
            )
        );

        $hint = Str::of(
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
