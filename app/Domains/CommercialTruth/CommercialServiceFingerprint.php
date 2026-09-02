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

        $commercialComponents =
            $this->commercialComponents(
                $normalised
            );

        /*
         * A line spanning three or more materially different
         * commercial component families is not safe to assign
         * wholesale to whichever keyword happens to match first.
         *
         * Two-family descriptions remain with the existing
         * classifier to avoid over-detecting ordinary bundled
         * service wording such as consultancy + support.
         */
        $isComposite =
            count(
                $commercialComponents
            ) >= 3;

        $serviceType =
            $isComposite
                ? 'composite'
                : $this->serviceType(
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

        /*
         * Preserve the historic fingerprint formula for every
         * non-composite service.
         *
         * Existing canonical reconciliations depend on those
         * identities remaining stable.
         */
        $fingerprintParts = [
            $serviceType,
            strtolower(
                $identityHint
                ?? ''
            ),
        ];

        if ($isComposite) {
            $fingerprintParts[] =
                implode(
                    ',',
                    $commercialComponents
                );
        }

        return [
            'service_type' => $serviceType,

            'service_hint' => $serviceHint,

            'commercial_components' => $commercialComponents,

            'commercial_treatment' => $this->commercialTreatment(
                $serviceType
            ),

            'classification_confidence' => $this->confidence(
                $serviceType
            ),

            'fingerprint' => hash(
                'sha256',
                implode(
                    '|',
                    $fingerprintParts
                )
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

    /**
     * Material commercial activity families explicitly present
     * in one source invoice description.
     *
     * This identifies evidence shape only. It does not allocate
     * money between the components.
     */
    private function commercialComponents(
        string $description
    ): array {
        $components = [];

        if (
            Str::contains(
                $description,
                [
                    'retainer',
                    'consultancy',
                ]
            )
        ) {
            $components[] = 'retainer';
        }

        if (
            Str::contains(
                $description,
                [
                    'support',
                    'maintenance',
                ]
            )
        ) {
            $components[] = 'support';
        }

        if (
            Str::contains(
                $description,
                [
                    'web design',
                    'website design',
                    'web development',
                    'website development',
                    'web dev',
                    'website dev',
                    'site dev',
                    'app development',
                    'application development',
                    'app dev',
                    'application dev',
                    'development work',
                    'microsite',
                ]
            )
        ) {
            $components[] = 'development';
        }

        if (
            Str::contains(
                $description,
                [
                    'seo',
                    'search engine optimisation',
                    'search engine optimization',
                ]
            )
        ) {
            $components[] = 'seo';
        }

        /*
         * "Content delivery network" is CDN evidence, not a
         * content-marketing component.
         *
         * Remove the CDN phrase before looking for a separately
         * named content activity so a line such as
         * "Content Delivery Network / SEO / Content" can still
         * identify both CDN and content truthfully.
         */
        $contentDescription =
            Str::of(
                $description
            )
                ->replace(
                    'content delivery network',
                    ''
                )
                ->toString();

        if (
            Str::contains(
                $contentDescription,
                [
                    'content marketing',
                    'content creation',
                    'copywriting',
                ]
            )
            || preg_match(
                '/\bcontent\b/i',
                $contentDescription
            ) === 1
        ) {
            $components[] = 'content';
        }

        if (
            Str::contains(
                $description,
                [
                    'advertising spend',
                    'advertising budget',
                    'ad spend',
                    'media spend',
                    'media budget',
                ]
            )
        ) {
            $components[] = 'media_spend';
        }

        if (
            Str::contains(
                $description,
                [
                    'ppc management',
                    'paid management - ppc',
                    'paid search management',
                    'google ads management',
                ]
            )
        ) {
            $components[] = 'ppc_management';
        }

        if (
            Str::contains(
                $description,
                [
                    'social media',
                    'social management',
                ]
            )
        ) {
            $components[] = 'social_media';
        }

        if (
            Str::contains(
                $description,
                [
                    'hosting',
                    'server hosting',
                    'website hosting',
                ]
            )
        ) {
            $components[] = 'hosting';
        }

        if (
            Str::contains(
                $description,
                [
                    'microsoft 365',
                    'office 365',
                    'mso365',
                    'm365',
                    'exchange online',
                ]
            )
        ) {
            $components[] = 'microsoft365';
        }

        if (
            Str::contains(
                $description,
                [
                    'google workspace',
                    'g suite',
                ]
            )
        ) {
            $components[] = 'google_workspace';
        }

        if (
            Str::contains(
                $description,
                [
                    'mailchimp',
                    'postmark',
                ]
            )
        ) {
            $components[] = 'email_platform';
        }

        if (
            Str::contains(
                $description,
                [
                    'nitro cdn',
                    'cdn',
                    'content delivery network',
                ]
            )
        ) {
            $components[] = 'cdn';
        }

        if (
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
            )
        ) {
            $components[] = 'domain';
        }

        return array_values(
            array_unique(
                $components
            )
        );
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
            'composite' => 'composite_candidate',

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
            'composite' => 95,

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
