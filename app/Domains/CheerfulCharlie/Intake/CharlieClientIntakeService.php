<?php

namespace App\Domains\CheerfulCharlie\Intake;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Domains\BusinessMemory\Insights\BusinessMemoryInsightService;
use App\Domains\BusinessMemory\Theories\BusinessMemoryTheoryService;
use App\Domains\CheerfulCharlie\Briefing\CharlieClientBriefService;
use App\Models\Client;
use Carbon\CarbonInterface;

class CharlieClientIntakeService
{
    public function __construct(
        private CreateBusinessMemory $memories,
        private AddBusinessMemoryEntry $entries,
        private BusinessMemoryExtractionService $extraction,
        private BusinessMemoryTheoryService $theories,
        private BusinessMemoryInsightService $insights,
        private CharlieClientBriefService $briefs
    ) {}

    public function ingest(
        Client $client,
        string $content,
        BusinessMemoryEntryType $type = BusinessMemoryEntryType::Note,
        string $source = 'charlie_intake',
        ?CarbonInterface $occurredAt = null,
        int $confidence = 100,
        bool $verified = false,
        array $metadata = []
    ): array {
        $memory = $this->memories
            ->execute($client);

        $entry = $this->entries
            ->execute(
                memory: $memory,
                type: $type,
                content: $content,
                occurredAt: $occurredAt,
                source: $source,
                confidence: $confidence,
                verified: $verified,
                metadata: $metadata
            );

        $observations = $this->extraction
            ->extract($entry);

        $theories = $this->theories
            ->rebuild($memory);

        $insights = $this->insights
            ->rebuild($memory);

        return [
            'entry' => $entry,

            'observations' => $observations,

            'theories' => $theories,

            'insights' => $insights,

            'brief' => $this->briefs
                ->build($client),
        ];
    }
}
