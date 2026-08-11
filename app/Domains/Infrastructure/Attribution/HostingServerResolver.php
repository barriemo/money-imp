<?php

namespace App\Domains\Infrastructure\Attribution;

use App\Models\AttributionCandidate;
use App\Models\ManagedService;
use Illuminate\Support\Collection;

class HostingServerResolver
{
    public function resolve(): Collection
    {
        return AttributionCandidate::query()
            ->where(
                'subject_type',
                'client'
            )
            ->where(
                'relationship_type',
                'hosted_on'
            )
            ->whereNull(
                'target_id'
            )
            ->whereIn(
                'status',
                [
                    'candidate',
                    'confirmed',
                ]
            )
            ->get()
            ->map(
                fn (
                    AttributionCandidate $candidate
                ) => $this->resolveCandidate(
                    $candidate
                )
            )
            ->filter()
            ->values();
    }

    public function resolveCandidate(
        AttributionCandidate $candidate
    ): ?AttributionCandidate {
        $servers =
            ManagedService::query()
                ->where(
                    'client_id',
                    $candidate->subject_id
                )
                ->where(
                    'status',
                    'active'
                )
                ->where(
                    'service_type',
                    'managed_hosting'
                )
                ->get()
                ->flatMap(
                    fn (ManagedService $service) => $service
                        ->assets()
                        ->where(
                            'asset_type',
                            'hosting_server'
                        )
                        ->get()
                )
                ->unique(
                    'id'
                )
                ->values();

        if ($servers->count() !== 1) {
            return null;
        }

        $server =
            $servers->first();

        $candidate->update([
            'target_type' => 'supplier_asset',

            'target_id' => $server->id,

            'confidence' => 100,

            'status' => 'confirmed',

            'reason' => 'Client has exactly one hosting server linked to its active managed hosting service.',

            'metadata' => array_merge(
                $candidate->metadata
                ?? [],
                [
                    'resolved_by' => 'managed_service_asset',

                    'resolved_at' => now()
                        ->toIso8601String(),
                ]
            ),
        ]);

        return $candidate->fresh();
    }
}
