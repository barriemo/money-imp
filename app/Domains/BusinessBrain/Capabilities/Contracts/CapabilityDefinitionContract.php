<?php

namespace App\Domains\BusinessBrain\Capabilities\Contracts;

interface CapabilityDefinitionContract
{
    public function definition(): array;

    public function actions(): array;
}
