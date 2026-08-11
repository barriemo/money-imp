<?php

namespace App\Domains\BusinessMemory\Context\Extraction;

use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Models\BusinessMemoryEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessContextExtractionService
{
    public function __construct(
        private BusinessContextService $context
    ) {}

    public function extract(
        BusinessMemoryEntry $entry
    ): Collection {
        $entry->loadMissing(
            'memory'
        );

        $contexts = collect();

        foreach (
            preg_split(
                '/(?<=[.!?])\s+/',
                trim($entry->content),
                -1,
                PREG_SPLIT_NO_EMPTY
            ) as $sentence
        ) {
            $definition =
                $this->classify(
                    trim($sentence)
                );

            if (! $definition) {
                continue;
            }

            [
                $type,
                $key,
                $confidence,
            ] = $definition;

            $contexts->push(
                $this->context->remember(
                    memory: $entry->memory,
                    type: $type,
                    key: $key,
                    value: trim($sentence),
                    confidence: $confidence,
                    verified: $entry->verified,
                    source: $entry->source,
                    metadata: [
                        'business_memory_entry_id' => $entry->id,
                    ]
                )
            );
        }

        return $contexts;
    }

    private function classify(
        string $sentence
    ): ?array {
        $text = Str::lower(
            $sentence
        );

        if (
            $this->containsAny(
                $text,
                [
                    'prefer ',
                    'prefers ',
                    'favour ',
                    'favours ',
                    'only spends ',
                    'happy to spend ',
                ]
            )
        ) {
            return [
                BusinessContextType::CommercialPreference,
                'buying_behaviour',
                90,
            ];
        }

        if (
            $this->containsAny(
                $text,
                [
                    'another nursery',
                    'another office',
                    'another location',
                    'second location',
                    'second site',
                    'new office',
                    'new location',
                    'new site',
                ]
            )
        ) {
            return [
                BusinessContextType::GrowthPlan,
                'expansion',
                80,
            ];
        }

        if (
            str_contains(
                $text,
                'hosting'
            )
            && $this->containsAny(
                $text,
                [
                    'think',
                    'believe',
                    'expect',
                    'assume',
                ]
            )
            && $this->containsAny(
                $text,
                [
                    'include',
                    'included',
                ]
            )
        ) {
            return [
                BusinessContextType::ServiceExpectation,
                'hosting_expectation',
                90,
            ];
        }

        if (
            $this->containsAny(
                $text,
                [
                    'been with us for',
                    'client since ',
                    'worked with us for',
                    'customer since ',
                ]
            )
        ) {
            return [
                BusinessContextType::Background,
                'relationship_history',
                90,
            ];
        }

        return null;
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
