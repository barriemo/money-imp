<?php

namespace App\Domains\BusinessBrain\BusinessState;

class BusinessStateGap
{
    public function __construct(
        public string $domain,

        public string $type,

        public string $scope,

        public ?string $clientId,

        public ?string $client,

        public string $title,

        public string $description
    ) {}
}
