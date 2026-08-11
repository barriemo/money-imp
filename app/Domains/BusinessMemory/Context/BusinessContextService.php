<?php

namespace App\Domains\BusinessMemory\Context;

use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use Illuminate\Support\Collection;

class BusinessContextService
{
    public function remember(
        BusinessMemory $memory,
        BusinessContextType $type,
        string $key,
        string $value,
        int $confidence = 100,
        bool $verified = false,
        string $source = 'manual',
        array $metadata = []
    ): BusinessContext {
        return BusinessContext::updateOrCreate(
            [
                'business_memory_id' => $memory->id,

                'context_type' => $type->value,

                'key' => $key,
            ],
            [
                'value' => trim($value),

                'confidence' => max(
                    0,
                    min(
                        100,
                        $confidence
                    )
                ),

                'verified' => $verified,

                'source' => $source,

                'metadata' => $metadata,
            ]
        );
    }

    public function active(
        BusinessMemory $memory
    ): Collection {
        return BusinessContext::query()
            ->where(
                'business_memory_id',
                $memory->id
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereNull(
                            'effective_from'
                        )
                        ->orWhere(
                            'effective_from',
                            '<=',
                            now()
                        );
                }
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereNull(
                            'effective_until'
                        )
                        ->orWhere(
                            'effective_until',
                            '>',
                            now()
                        );
                }
            )
            ->orderBy(
                'context_type'
            )
            ->orderBy(
                'key'
            )
            ->get();
    }
}
