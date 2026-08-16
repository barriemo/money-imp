<?php

namespace App\Domains\OperatingSystem\Registries;

use App\Domains\OperatingSystem\DTOs\CapabilityDefinition;
use Illuminate\Support\Collection;

class CapabilityRegistry
{
    public function all(): Collection
    {
        return collect([
            new CapabilityDefinition(
                key: 'conversation',
                name: 'Business Conversation',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'conversation_context',
                name: 'Conversation Context',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'assertions',
                name: 'Business Assertions',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'investigations',
                name: 'Investigations',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'investigation_reassessment',
                name: 'Investigation Reassessment',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'investigation_timeline',
                name: 'Investigation Timeline',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'business_experience',
                name: 'Business Experience',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'evidence_bus',
                name: 'Evidence Bus',
                owner: 'business_brain',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'business_briefing',
                name: 'Business Briefing',
                owner: 'business_brain',
                phase: 'deploy',
                status: 'in_progress',
            ),

            new CapabilityDefinition(
                key: 'cash_truth',
                name: 'Cash Truth',
                owner: 'cfo',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'payment_truth',
                name: 'Payment Truth',
                owner: 'cfo',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'credit_truth',
                name: 'Credit Truth',
                owner: 'cfo',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'financial_position',
                name: 'Financial Position',
                owner: 'cfo',
                phase: 'connect',
                status: 'in_progress',
            ),

            new CapabilityDefinition(
                key: 'reconciliation',
                name: 'Financial Reconciliation',
                owner: 'cfo',
                phase: 'connect',
                status: 'deployed',
            ),

            new CapabilityDefinition(
                key: 'sales_intelligence',
                name: 'Sales Intelligence',
                owner: 'sales',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'commercial_truth',
                name: 'Commercial Truth',
                owner: 'commercial',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'project_truth',
                name: 'Project Truth',
                owner: 'project',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'delivery_truth',
                name: 'Delivery Truth',
                owner: 'delivery',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'work_truth',
                name: 'Work Truth',
                owner: 'team',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'billability_truth',
                name: 'Billability Truth',
                owner: 'billability',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'invoice_truth',
                name: 'Invoice Truth',
                owner: 'invoice',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'client_health',
                name: 'Client Health',
                owner: 'client_success',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'strategic_direction',
                name: 'Strategic Direction',
                owner: 'ceo',
                phase: 'scaffold',
                status: 'planned',
            ),

            new CapabilityDefinition(
                key: 'executive_coordination',
                name: 'Executive Coordination',
                owner: 'chief_of_staff',
                phase: 'scaffold',
                status: 'planned',
            ),
        ]);
    }

    public function forSpecialist(string $specialist): Collection
    {
        return $this->all()
            ->where(
                'owner',
                $specialist
            )
            ->values();
    }
}
