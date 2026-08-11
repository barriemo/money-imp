<?php

namespace App\Domains\CheerfulCharlie\Briefing;

use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\BusinessMemory\Services\BusinessMemoryTimelineService;
use App\Domains\CheerfulCharlie\Priority\CharliePriorityEngine;
use App\Domains\ManagedServices\Services\ManagedServiceTruthService;
use App\Models\BusinessMemoryInsight;
use App\Models\BusinessMemoryTheory;
use App\Models\Client;
use App\Models\ManagedService;

class CharlieClientBriefService
{
    public function __construct(
        private CreateBusinessMemory $memories,
        private BusinessContextService $context,
        private BusinessMemoryTimelineService $timeline,
        private CharliePriorityEngine $priorities,
        private ManagedServiceTruthService $managedTruth
    ) {}

    public function build(
        Client $client
    ): array {
        $memory = $this->memories
            ->execute(
                $client
            );

        $recentMemory = $this->timeline
            ->timeline(
                memory: $memory,
                limit: 20
            );

        $contexts = $this->context
            ->active(
                $memory
            );

        $theories = BusinessMemoryTheory::query()
            ->where(
                'business_memory_id',
                $memory->id
            )
            ->where(
                'status',
                'active'
            )
            ->orderByDesc(
                'confidence'
            )
            ->get();

        $insights = BusinessMemoryInsight::query()
            ->where(
                'business_memory_id',
                $memory->id
            )
            ->where(
                'status',
                'open'
            )
            ->get();

        $rankedPriorities = $this->priorities
            ->rank(
                $insights
            );

        $managedServices = ManagedService::query()
            ->where(
                'client_id',
                $client->id
            )
            ->where(
                'status',
                'active'
            )
            ->get()
            ->map(
                fn (ManagedService $service) => $this->managedTruth
                    ->summary(
                        $service
                    )
            );

        return [
            'client' => $client,

            'memory' => $memory,

            'context' => $contexts,

            'recent_memory' => $recentMemory,

            'theories' => $theories,

            'insights' => $insights,

            'priorities' => $rankedPriorities,

            'managed_services' => $managedServices,

            'summary' => [
                'context_count' => $contexts->count(),

                'memory_count' => $memory
                    ->entries()
                    ->count(),

                'theory_count' => $theories->count(),

                'insight_count' => $insights->count(),

                'managed_service_count' => $managedServices->count(),
            ],
        ];
    }
}
