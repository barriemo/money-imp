<?php

namespace App\Domains\BusinessBrain\MorningBrief\Services;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshot;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshotRepository;
use App\Domains\BusinessBrain\MorningBrief\MorningBrief;
use App\Domains\BusinessBrain\MorningBrief\MorningBriefBuilder;

class MorningBriefService
{
    public function __construct(
        private MorningBriefBuilder $builder,

        private MorningBriefSnapshotRepository $snapshots
    ) {}

    public function build(
        AttentionContext $context
    ): MorningBrief {
        $brief =
            $this->builder->build(
                $context
            );

        $this->snapshots->store(
            new MorningBriefSnapshot(
                client: $context->client,

                signalCount: $brief->signals->count(),

                signals: $brief->signals,

                generatedAt: now()
            )
        );

        return $brief;
    }
}
