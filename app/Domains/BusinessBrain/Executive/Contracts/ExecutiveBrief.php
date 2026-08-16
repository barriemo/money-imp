<?php

namespace App\Domains\BusinessBrain\Executive\Contracts;

interface ExecutiveBrief
{
    public function confidence(): int;

    public function status(): string;
}
