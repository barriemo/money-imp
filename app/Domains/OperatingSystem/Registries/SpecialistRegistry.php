<?php

namespace App\Domains\OperatingSystem\Registries;

use App\Domains\OperatingSystem\DTOs\SpecialistDefinition;
use Illuminate\Support\Collection;

class SpecialistRegistry
{
    public function all(): Collection
    {
        return collect([
            new SpecialistDefinition(
                key: 'business_brain',
                name: 'Business Brain',
                purpose: 'Shared evidence, truth, conversation, investigation, memory and reasoning.',
                phase: 'foundation',
                status: 'in_progress',
            ),

            new SpecialistDefinition(
                key: 'cfo',
                name: 'CFO Imp',
                purpose: 'Establish and explain the financial truth of the business.',
                phase: 'deploy',
                status: 'in_progress',
                dependencies: [
                    'business_brain',
                ],
            ),

            new SpecialistDefinition(
                key: 'sales',
                name: 'Sales Imp',
                purpose: 'Identify, qualify and develop new business opportunities.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'business_brain',
                ],
            ),

            new SpecialistDefinition(
                key: 'commercial',
                name: 'Commercial Imp',
                purpose: 'Turn human-approved client agreements into commercial truth.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'sales',
                ],
            ),

            new SpecialistDefinition(
                key: 'project',
                name: 'Project Imp',
                purpose: 'Turn commercial obligations into executable delivery plans.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'commercial',
                ],
            ),

            new SpecialistDefinition(
                key: 'delivery',
                name: 'Delivery Imp',
                purpose: 'Establish what has actually been delivered, blocked, reviewed and completed.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'project',
                ],
            ),

            new SpecialistDefinition(
                key: 'team',
                name: 'Team Imp',
                purpose: 'Understand team work, time, capacity, utilisation and allocation.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'delivery',
                ],
            ),

            new SpecialistDefinition(
                key: 'billability',
                name: 'Billability Imp',
                purpose: 'Reconcile commercial entitlement against actual work and identify leakage.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'commercial',
                    'project',
                    'delivery',
                    'team',
                ],
            ),

            new SpecialistDefinition(
                key: 'invoice',
                name: 'Invoice Imp',
                purpose: 'Convert billable commercial truth into accurate invoicing and collection.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'billability',
                    'cfo',
                ],
            ),

            new SpecialistDefinition(
                key: 'client_success',
                name: 'Client Success Imp',
                purpose: 'Understand client health, retention, relationships, risk and growth.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'commercial',
                    'delivery',
                    'cfo',
                ],
            ),

            new SpecialistDefinition(
                key: 'ceo',
                name: 'CEO Imp',
                purpose: 'Reason across the whole business and support strategic decisions.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'cfo',
                    'sales',
                    'commercial',
                    'delivery',
                    'team',
                ],
            ),

            new SpecialistDefinition(
                key: 'chief_of_staff',
                name: 'Chief of Staff Imp',
                purpose: 'Coordinate specialist priorities and surface what needs human attention.',
                phase: 'planned',
                status: 'planned',
                dependencies: [
                    'cfo',
                    'sales',
                    'commercial',
                    'project',
                    'delivery',
                    'team',
                    'billability',
                    'invoice',
                    'client_success',
                    'ceo',
                ],
            ),
        ]);
    }

    public function find(string $key): ?SpecialistDefinition
    {
        return $this->all()
            ->first(
                fn (SpecialistDefinition $specialist) => $specialist->key === $key
            );
    }
}
