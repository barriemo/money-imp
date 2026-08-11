<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;

class CharlieAnswerIngestionService
{
    public function ingest(
        BusinessMemory $memory,
        array $question,
        string $answer,
        int $confidence = 100
    ): BusinessContext {
        return app(
            BusinessContextService::class
        )->remember(
            memory: $memory,
            type: $question['type'],
            key: $question['key'],
            value: trim($answer),
            confidence: $confidence,
            verified: true,
            source: 'charlie_answer'
        );
    }
}
