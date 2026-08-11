<?php

namespace App\Domains\ManagedServices\Templates;

use App\Domains\ManagedServices\DTOs\ManagedServiceCompleteness;
use App\Models\ManagedService;
use App\Models\ManagedServiceTemplate;

class ManagedServiceTemplateEvaluator
{
    public function evaluate(
        ManagedService $service
    ): ManagedServiceCompleteness {
        $service->loadMissing(
            'assets'
        );

        $template = ManagedServiceTemplate::query()
            ->with('requirements')
            ->where(
                'service_type',
                $service->service_type
            )
            ->where(
                'active',
                true
            )
            ->firstOrFail();

        $present = [];
        $missing = [];
        $recommendedMissing = [];

        $earnedWeight = 0;
        $possibleWeight = 0;

        foreach (
            $template->requirements as $requirement
        ) {
            $count = $service->assets
                ->filter(
                    fn ($asset) => $this->matches(
                        $requirement
                            ->component_type,
                        $asset->asset_type
                    )
                )
                ->count();

            $satisfied =
                $count
                >= $requirement
                    ->minimum_count;

            $possibleWeight +=
                $requirement->weight;

            if ($satisfied) {
                $earnedWeight +=
                    $requirement->weight;

                $present[] = [
                    'type' => $requirement
                        ->component_type,

                    'name' => $requirement->name,

                    'count' => $count,
                ];

                continue;
            }

            $item = [
                'type' => $requirement
                    ->component_type,

                'name' => $requirement->name,

                'minimum_count' => $requirement
                    ->minimum_count,

                'found_count' => $count,
            ];

            if ($requirement->required) {
                $missing[] = $item;
            } else {
                $recommendedMissing[] =
                    $item;
            }
        }

        $score =
            $possibleWeight > 0
                ? round(
                    (
                        $earnedWeight
                        / $possibleWeight
                    ) * 100,
                    2
                )
                : 100.0;

        return new ManagedServiceCompleteness(
            service: $service,
            template: $template,
            score: $score,
            present: $present,
            missing: $missing,
            recommendedMissing: $recommendedMissing,
        );
    }

    private function matches(
        string $component,
        string $assetType
    ): bool {
        return match ($component) {
            'hosting_server' => $assetType
                    === 'hosting_server',

            'control_panel' => $assetType
                    === 'hosting_addon',

            'backup' => in_array(
                $assetType,
                [
                    'backup',
                    'storage',
                ],
                true
            ),

            'dns' => $assetType === 'dns',

            'ssl' => $assetType === 'ssl',

            'monitoring' => $assetType === 'monitoring',

            default => $assetType
                    === $component,
        };
    }
}
