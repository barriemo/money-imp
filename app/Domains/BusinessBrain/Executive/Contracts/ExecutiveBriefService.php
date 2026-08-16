<?php

namespace App\Domains\BusinessBrain\Executive\Contracts;

interface ExecutiveBriefService
{
    public function current(): ExecutiveBrief;
}
