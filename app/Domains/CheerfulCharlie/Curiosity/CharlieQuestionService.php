<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Models\BusinessMemory;

class CharlieQuestionService
{
    public function next(
        BusinessMemory $memory
    ): ?array {
        return app(
            KnowledgeGapService::class
        )
            ->gaps($memory)
            ->first();
    }
}
