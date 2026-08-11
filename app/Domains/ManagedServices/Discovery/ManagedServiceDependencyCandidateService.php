<?php

namespace App\Domains\ManagedServices\Discovery;

use App\Domains\ManagedServices\Templates\ManagedServiceTemplateEvaluator;
use App\Models\ManagedService;
use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class ManagedServiceDependencyCandidateService
{
    public function __construct(
        private ManagedServiceTemplateEvaluator $evaluator
    ) {}

    public function candidates(
        ManagedService $service
    ): Collection {
        $service->loadMissing([
            'assets.outgoingRelationships.toAsset',
            'assets.incomingRelationships.fromAsset',
        ]);

        $completeness =
            $this->evaluator->evaluate(
                $service
            );

        $missingTypes = collect([
            ...$completeness->missing,
            ...$completeness->recommendedMissing,
        ])
            ->pluck('type')
            ->unique()
            ->values();

        $existingIds =
            $service->assets
                ->pluck('id')
                ->all();

        $candidates = collect();

        foreach ($service->assets as $asset) {
            foreach (
                $asset->outgoingRelationships as $relationship
            ) {
                $candidate =
                    $relationship->toAsset;

                if (
                    ! $candidate
                    || in_array(
                        $candidate->id,
                        $existingIds,
                        true
                    )
                ) {
                    continue;
                }

                $component =
                    $this->componentFor(
                        $candidate
                    );

                if (
                    ! $component
                    || ! $missingTypes
                        ->contains($component)
                ) {
                    continue;
                }

                $candidates->push(
                    $this->candidate(
                        asset: $candidate,
                        component: $component,
                        relationship: $relationship
                            ->relationship,
                        confidence: $relationship
                            ->confidence,
                        verified: $relationship
                            ->verified
                    )
                );
            }

            foreach (
                $asset->incomingRelationships as $relationship
            ) {
                $candidate =
                    $relationship->fromAsset;

                if (
                    ! $candidate
                    || in_array(
                        $candidate->id,
                        $existingIds,
                        true
                    )
                ) {
                    continue;
                }

                $component =
                    $this->componentFor(
                        $candidate
                    );

                if (
                    ! $component
                    || ! $missingTypes
                        ->contains($component)
                ) {
                    continue;
                }

                $candidates->push(
                    $this->candidate(
                        asset: $candidate,
                        component: $component,
                        relationship: $relationship
                            ->relationship,
                        confidence: $relationship
                            ->confidence,
                        verified: $relationship
                            ->verified
                    )
                );
            }
        }

        return $candidates
            ->unique(
                fn (array $item) => $item['asset']->id
                    .'|'
                    .$item['component_type']
            )
            ->values();
    }

    private function componentFor(
        SupplierAsset $asset
    ): ?string {
        return match (
            $asset->asset_type
        ) {
            'hosting_server' => 'hosting_server',

            'hosting_addon' => 'control_panel',

            'backup',
            'storage' => 'backup',

            'dns' => 'dns',

            'ssl' => 'ssl',

            'monitoring' => 'monitoring',

            default => null,
        };
    }

    private function candidate(
        SupplierAsset $asset,
        string $component,
        string $relationship,
        int $confidence,
        bool $verified
    ): array {
        return [
            'asset' => $asset,

            'component_type' => $component,

            'relationship' => $relationship,

            'confidence' => $confidence,

            'verified_relationship' => $verified,

            'recommended' => $verified
                && $confidence >= 90,
        ];
    }
}
