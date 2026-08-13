<?php

namespace App\Domains\VATIntelligence\Providers;

use App\Domains\VATIntelligence\VATExposure;
use App\Domains\VATIntelligence\VATExposureBuilder;
use App\Domains\VATIntelligence\VATPositionRepository;

class ClientVATExposureProvider
{
    public function __construct(
        private VATPositionRepository $repository,

        private VATExposureBuilder $builder
    ) {}

    public function provide(
        string $clientId
    ): ?VATExposure {
        $position =
            $this->repository->findForClient(
                $clientId
            );

        if (! $position) {
            return null;
        }

        return $this->builder->build(
            $position
        );
    }
}
