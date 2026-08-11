<?php

namespace App\Domains\BusinessMemory\Extraction;

use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use App\Models\BusinessMemoryEntry;
use App\Models\BusinessMemoryObservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessMemoryExtractionService
{
    public function extract(
        BusinessMemoryEntry $entry
    ): Collection {
        $content = trim(
            $entry->content
        );

        if ($content === '') {
            return collect();
        }

        $observations = collect();

        foreach (
            preg_split(
                '/(?<=[.!?])\s+/',
                $content,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) as $sentence
        ) {
            $type = $this->classify(
                $sentence
            );

            if (! $type) {
                continue;
            }

            $observations->push(
                BusinessMemoryObservation::updateOrCreate(
                    [
                        'business_memory_entry_id' => $entry->id,

                        'observation_type' => $type->value,

                        'statement' => trim($sentence),
                    ],
                    [
                        'business_memory_id' => $entry->business_memory_id,

                        'confidence' => $this->confidenceFor(
                            $type
                        ),

                        'verified' => false,

                        'source' => 'rule',
                    ]
                )
            );
        }

        return $observations;
    }

    private function classify(
        string $sentence
    ): ?BusinessMemoryObservationType {
        $text = Str::lower(
            trim($sentence)
        );

        if (
            str_contains($text, '?')
            || Str::startsWith(
                $text,
                [
                    'should ',
                    'can ',
                    'could ',
                    'do ',
                    'does ',
                    'is ',
                    'are ',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Question;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'promised',
                    'agreed to',
                    'will send',
                    'will do',
                    'committed to',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Promise;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'worried',
                    'concerned',
                    'concern',
                    'nervous',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Concern;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'risk',
                    'could fail',
                    'might fail',
                    'no backup',
                    'no backups',
                    'vulnerable',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Risk;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'opportunity',
                    'could sell',
                    'upsell',
                    'cross sell',
                    'interested in',
                    'asked about',
                    'looking at',
                    'considering',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Opportunity;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'decided',
                    'decision',
                    'agreed that',
                    'approved',
                    'rejected',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Decision;
        }

        if (
            $this->containsAny(
                $text,
                [
                    'need ',
                    'needs ',
                    'require ',
                    'requires ',
                    'must ',
                ]
            )
        ) {
            return BusinessMemoryObservationType::Requirement;
        }

        return null;
    }

    private function confidenceFor(
        BusinessMemoryObservationType $type
    ): int {
        return match ($type) {
            BusinessMemoryObservationType::Promise,
            BusinessMemoryObservationType::Decision => 90,

            BusinessMemoryObservationType::Requirement => 85,

            BusinessMemoryObservationType::Question => 100,

            default => 75,
        };
    }

    private function containsAny(
        string $text,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            if (
                str_contains(
                    $text,
                    $needle
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
