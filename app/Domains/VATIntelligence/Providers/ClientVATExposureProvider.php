<?php

namespace App\Domains\VATIntelligence\Providers;

use App\Domains\VATIntelligence\VATExposure;
use App\Domains\VATIntelligence\VATExposureBuilder;
use App\Domains\VATIntelligence\VATPosition;

class ClientVATExposureProvider
{
    public function __construct(
        private VATExposureBuilder $builder
    ) {}

    public function provide(
        VATPosition $position
    ): VATExposure {
        return $this->builder->build(
            $position
        );
    }
}
