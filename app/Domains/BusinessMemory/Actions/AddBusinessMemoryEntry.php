<?php

namespace App\Domains\BusinessMemory\Actions;

use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryEntry;
use Carbon\CarbonInterface;

class AddBusinessMemoryEntry
{
    public function execute(
        BusinessMemory $memory,
        BusinessMemoryEntryType $type,
        string $content,
        ?CarbonInterface $occurredAt = null,
        string $source = 'manual',
        ?string $sourceReference = null,
        int $confidence = 100,
        bool $verified = false,
        array $metadata = []
    ): BusinessMemoryEntry {
        return BusinessMemoryEntry::create([
            'business_memory_id' => $memory->id,

            'entry_type' => $type,

            'occurred_at' => $occurredAt ?? now(),

            'content' => trim($content),

            'source' => $source,

            'source_reference' => $sourceReference,

            'confidence' => max(
                0,
                min(
                    100,
                    $confidence
                )
            ),

            'verified' => $verified,

            'metadata' => $metadata,
        ]);
    }
}
