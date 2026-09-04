<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use App\Domains\BusinessBrain\BusinessState\BusinessStateService;

class BusinessStateBaselineCaptureService
{
    public function __construct(
        private BusinessStateService $states,
        private BusinessStateBaselineFactory $factory,
        private BusinessStateBaselineSnapshotRepository $repository
    ) {}

    public function capture(): BusinessStateBaseline
    {
        $baseline =
            $this->factory->fromState(
                $this->states->current()
            );

        $this->repository->store(
            $baseline
        );

        return $baseline;
    }
}
