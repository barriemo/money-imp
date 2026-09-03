<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Models\Client;

final class CeoSignalClientResolver
{
    /**
     * Resolve only when one client is materially more likely
     * than the alternatives.
     *
     * Ambiguity is preferable to silently choosing the
     * wrong financial subject.
     */
    public function resolve(
        string $input
    ): ?Client {
        $normalisedInput =
            $this->normalise(
                $input
            );

        $inputTokens =
            $this->tokens(
                $normalisedInput
            );

        $clients =
            Client::query()
                ->orderBy(
                    'name'
                )
                ->get();

        if ($clients->isEmpty()) {
            return null;
        }

        $frequencies =
            $this->tokenFrequencies(
                $clients
            );

        $matches =
            $clients
                ->map(
                    fn (Client $client) => [
                        'client' => $client,

                        'score' => $this->score(
                            input: $normalisedInput,

                            inputTokens: $inputTokens,

                            clientName: $client->name,

                            tokenFrequencies: $frequencies
                        ),
                    ]
                )
                ->filter(
                    fn (array $match) => $match['score'] > 0
                )
                ->sortByDesc(
                    'score'
                )
                ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $best =
            $matches->first();

        $second =
            $matches->get(
                1
            );

        /*
         * Similar client names must not be guessed.
         */
        if (
            $second !== null
            && (
                $best['score']
                - $second['score']
            ) < 100
        ) {
            return null;
        }

        return $best[
            'client'
        ];
    }

    private function score(
        string $input,
        array $inputTokens,
        string $clientName,
        array $tokenFrequencies
    ): int {
        $normalisedName =
            $this->normalise(
                $clientName
            );

        if (
            $normalisedName !== ''
            && $this->containsPhrase(
                $input,
                $normalisedName
            )
        ) {
            return
                100000
                + strlen(
                    $normalisedName
                );
        }

        $clientTokens =
            $this->significantTokens(
                $clientName
            );

        if ($clientTokens === []) {
            return 0;
        }

        $matched =
            array_values(
                array_intersect(
                    $clientTokens,
                    $inputTokens
                )
            );

        $matchedCount =
            count(
                $matched
            );

        if ($matchedCount >= 2) {
            $ratio =
                $matchedCount
                / count(
                    $clientTokens
                );

            return
                10000
                + ($matchedCount * 1000)
                + (int) round(
                    $ratio * 100
                );
        }

        /*
         * A single distinctive token may be enough:
         * "Walker", "Peak", "MML", etc.
         *
         * It must occur in only one client name.
         */
        if ($matchedCount === 1) {
            $token =
                $matched[
                    0
                ];

            if (
                strlen(
                    $token
                ) >= 3
                && (
                    $tokenFrequencies[
                        $token
                    ] ?? 0
                ) === 1
            ) {
                return
                    5000
                    + strlen(
                        $token
                    );
            }
        }

        return 0;
    }

    private function containsPhrase(
        string $haystack,
        string $needle
    ): bool {
        return preg_match(
            '/(?:^| )'
            .preg_quote(
                $needle,
                '/'
            )
            .'(?: |$)/',
            $haystack
        ) === 1;
    }

    private function tokenFrequencies(
        $clients
    ): array {
        $frequencies = [];

        foreach ($clients as $client) {
            foreach (
                array_unique(
                    $this->significantTokens(
                        $client->name
                    )
                ) as $token
            ) {
                $frequencies[
                    $token
                ] =
                    (
                        $frequencies[
                            $token
                        ] ?? 0
                    )
                    + 1;
            }
        }

        return $frequencies;
    }

    private function significantTokens(
        string $value
    ): array {
        $ignored = [
            'the',
            'and',
            'of',
            'ltd',
            'limited',
            'llp',
            'plc',
            'inc',
            'company',
            'group',
            'services',
            'service',
            'scotland',
        ];

        return array_values(
            array_filter(
                $this->tokens(
                    $this->normalise(
                        $value
                    )
                ),
                fn (string $token) => ! in_array(
                    $token,
                    $ignored,
                    true
                )
            )
        );
    }

    private function tokens(
        string $value
    ): array {
        return array_values(
            array_filter(
                preg_split(
                    '/\s+/',
                    trim(
                        $value
                    )
                ) ?: []
            )
        );
    }

    private function normalise(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                preg_replace(
                    '/[^a-z0-9]+/',
                    ' ',
                    strtolower(
                        $value
                    )
                ) ?? ''
            ) ?? ''
        );
    }
}
