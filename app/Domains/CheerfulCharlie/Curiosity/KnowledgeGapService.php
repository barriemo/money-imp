<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Domains\ManagedServices\Services\ManagedServiceTruthService;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use App\Models\Client;
use App\Models\ManagedService;
use Illuminate\Support\Collection;

class KnowledgeGapService
{
    public function __construct(
        private ManagedServiceTruthService $managedTruth
    ) {}

    public function gaps(
        BusinessMemory $memory
    ): Collection {
        $memory->loadMissing(
            'subject'
        );

        $known = BusinessContext::query()
            ->where(
                'business_memory_id',
                $memory->id
            )
            ->get()
            ->map(
                fn (BusinessContext $context) => $context->context_type->value
                    .'|'
                    .$context->key
            )
            ->all();

        $gaps = collect();

        if (
            $memory->subject
            instanceof Client
        ) {
            $gaps = $gaps->merge(
                $this->managedServiceGaps(
                    $memory->subject
                )
            );
        }

        $gaps = $gaps->merge(
            $this->generalGaps()
        );

        return $gaps
            ->reject(
                fn (array $gap) => in_array(
                    $gap['type']->value
                    .'|'
                    .$gap['key'],
                    $known,
                    true
                )
            )
            ->sortByDesc('priority')
            ->unique(
                fn (array $gap) => $gap['type']->value
                    .'|'
                    .$gap['key']
            )
            ->values();
    }

    private function managedServiceGaps(
        Client $client
    ): Collection {
        return ManagedService::query()
            ->where(
                'client_id',
                $client->id
            )
            ->where(
                'status',
                'active'
            )
            ->get()
            ->flatMap(
                function (
                    ManagedService $service
                ): Collection {
                    $truth =
                        $this->managedTruth
                            ->summary($service);

                    return collect([
                        ...$truth[
                            'missing_required'
                        ],
                        ...$truth[
                            'missing_recommended'
                        ],
                    ])
                        ->map(
                            fn (array $component) => $this->componentGap(
                                $service,
                                $component
                            )
                        )
                        ->filter();
                }
            )
            ->values();
    }

    private function componentGap(
        ManagedService $service,
        array $component
    ): ?array {
        $type =
            $component['type']
            ?? $component[
                'component_type'
            ]
            ?? null;

        if (! $type) {
            return null;
        }

        $serviceName =
            $service->name;

        return match ($type) {
            'backup' => [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'backup_provider',

                'question' => "Who currently looks after backups for {$serviceName}?",

                'reason' => 'Backup is missing from the known managed service and affects operational resilience.',

                'priority' => 100,

                'source' => 'managed_service_gap',

                'service_id' => $service->id,

                'component_type' => $type,
            ],

            'control_panel' => [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'control_panel_management',

                'question' => "Who manages the control panel for {$serviceName}?",

                'reason' => 'The managed service requires a control panel but ownership is not yet known.',

                'priority' => 94,

                'source' => 'managed_service_gap',

                'service_id' => $service->id,

                'component_type' => $type,
            ],

            'dns' => [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'dns_management',

                'question' => "Who manages DNS for {$serviceName}?",

                'reason' => 'DNS is part of the service model but is not yet accounted for.',

                'priority' => 92,

                'source' => 'managed_service_gap',

                'service_id' => $service->id,

                'component_type' => $type,
            ],

            'ssl' => [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'ssl_management',

                'question' => "Where is SSL managed for {$serviceName}?",

                'reason' => 'SSL responsibility is missing from the known service model.',

                'priority' => 90,

                'source' => 'managed_service_gap',

                'service_id' => $service->id,

                'component_type' => $type,
            ],

            'monitoring' => [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'monitoring_provider',

                'question' => "Is {$serviceName} actively monitored, and if so by whom?",

                'reason' => 'Monitoring is recommended but has not been identified.',

                'priority' => 82,

                'source' => 'managed_service_gap',

                'service_id' => $service->id,

                'component_type' => $type,
            ],

            default => null,
        };
    }

    private function generalGaps(): Collection
    {
        return collect([
            [
                'type' => BusinessContextType::DecisionMaker,

                'key' => 'primary_decision_maker',

                'question' => 'Who is the main decision maker?',

                'reason' => 'Knowing who makes buying decisions improves commercial recommendations.',

                'priority' => 75,

                'source' => 'general',
            ],

            [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'internet_provider',

                'question' => 'Who provides their internet connection?',

                'reason' => 'Connectivity ownership affects operational risk and service opportunity.',

                'priority' => 65,

                'source' => 'general',
            ],

            [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'backup_provider',

                'question' => 'Who currently looks after their backups?',

                'reason' => 'Backup ownership affects service completeness and operational risk.',

                'priority' => 80,

                'source' => 'general',
            ],

            [
                'type' => BusinessContextType::CurrentSupplier,

                'key' => 'telephony_provider',

                'question' => 'Who provides their phone system?',

                'reason' => 'Telephony may expose service gaps or commercial opportunities.',

                'priority' => 45,

                'source' => 'general',
            ],

            [
                'type' => BusinessContextType::Background,

                'key' => 'mfa_status',

                'question' => 'Do they use MFA across their main accounts?',

                'reason' => 'MFA materially affects security risk.',

                'priority' => 70,

                'source' => 'general',
            ],
        ]);
    }
}
