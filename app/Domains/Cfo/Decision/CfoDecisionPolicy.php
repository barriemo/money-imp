<?php

namespace App\Domains\Cfo\Decision;

interface CfoDecisionPolicy
{
    public function supports(
        CfoDecisionRequest $request
    ): bool;

    public function decide(
        CfoDecisionContext $context
    ): CfoDecision;
}
