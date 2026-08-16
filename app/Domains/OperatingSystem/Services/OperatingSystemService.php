<?php

namespace App\Domains\OperatingSystem\Services;

use App\Domains\OperatingSystem\Registries\CapabilityRegistry;
use App\Domains\OperatingSystem\Registries\SpecialistRegistry;
use Illuminate\Support\Collection;

class OperatingSystemService
{
    public function __construct(
        private SpecialistRegistry $specialists,
        private CapabilityRegistry $capabilities,
    ) {}

    public function specialists(): Collection
    {
        return $this->specialists->all();
    }

    public function capabilities(): Collection
    {
        return $this->capabilities->all();
    }

    public function capabilitiesFor(string $specialist): Collection
    {
        return $this->capabilities
            ->forSpecialist(
                $specialist
            );
    }

    public function nextRecommendedWork(): string
    {
        $conversation =
            $this->capabilities()
                ->firstWhere(
                    'key',
                    'conversation'
                );

        if (
            $conversation
            && $conversation->status !== 'deployed'
        ) {
            return 'Finish Business Brain conversation resolution and context hardening.';
        }

        $financialPosition =
            $this->capabilities()
                ->firstWhere(
                    'key',
                    'financial_position'
                );

        if (
            $financialPosition
            && $financialPosition->status !== 'deployed'
        ) {
            return 'Complete the unified CFO financial position and briefing layer.';
        }

        return 'Scaffold Sales Imp and establish Sales Intelligence evidence.';
    }
}
