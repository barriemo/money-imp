<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Communication\ProjectCommunicationMemory;
use App\Models\Project;
use Carbon\CarbonImmutable;

class ProjectCommunicationMemoryService
{
    public function latest(
        Project $project
    ): array {
        return $project
            ->communications()
            ->latest('occurred_at')
            ->get()
            ->map(
                fn ($communication) => new ProjectCommunicationMemory(
                    type: $communication->type,

                    direction: $communication->direction,

                    summary: $communication->summary,

                    commitment: $communication->commitment,

                    requestedBy: $communication->requested_by,

                    occurredAt: $communication->occurred_at
                        ? CarbonImmutable::parse(
                            $communication->occurred_at
                        )
                        : null,
                )
            )
            ->all();
    }
}
