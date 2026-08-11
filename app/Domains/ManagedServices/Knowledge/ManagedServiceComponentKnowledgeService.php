<?php

namespace App\Domains\ManagedServices\Knowledge;

use App\Models\ManagedService;
use App\Models\ManagedServiceComponentKnowledge;
use Illuminate\Support\Collection;

class ManagedServiceComponentKnowledgeService
{
    public function remember(
        ManagedService $service,
        string $componentType,
        string $value,
        string $state = 'known_unverified',
        int $confidence = 100,
        bool $verified = false,
        string $source = 'manual',
        ?string $sourceReference = null,
        array $metadata = []
    ): ManagedServiceComponentKnowledge {
        return ManagedServiceComponentKnowledge::updateOrCreate(
            [
                'managed_service_id' => $service->id,

                'component_type' => $componentType,
            ],
            [
                'state' => $state,

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

                'source_reference' => $sourceReference,

                'metadata' => $metadata,
            ]
        );
    }

    public function forService(
        ManagedService $service
    ): Collection {
        return ManagedServiceComponentKnowledge::query()
            ->where(
                'managed_service_id',
                $service->id
            )
            ->orderBy(
                'component_type'
            )
            ->get();
    }

    public function find(
        ManagedService $service,
        string $componentType
    ): ?ManagedServiceComponentKnowledge {
        return ManagedServiceComponentKnowledge::query()
            ->where(
                'managed_service_id',
                $service->id
            )
            ->where(
                'component_type',
                $componentType
            )
            ->first();
    }
}
