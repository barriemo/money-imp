<?php

namespace App\Domains\BusinessBrain\MorningBrief\History;

use Illuminate\Support\Collection;

class MorningBriefSnapshotRepository
{
    private Collection $snapshots;

    public function __construct()
    {
        $this->snapshots = collect();
    }

    public function store(
        MorningBriefSnapshot $snapshot
    ): void {
        $this->snapshots->push(
            $snapshot
        );
    }

    public function latest(): ?MorningBriefSnapshot
    {
        return $this->snapshots->last();
    }

    public function all(): Collection
    {
        return $this->snapshots;
    }
}
