<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Domains\BusinessMemory\Insights\BusinessMemoryInsightService;
use App\Domains\CheerfulCharlie\Review\CharlieFindingEngine;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieFindingDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_findings_are_collapsed(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute($client);

        $add = app(
            AddBusinessMemoryEntry::class
        );

        $extract = app(
            BusinessMemoryExtractionService::class
        );

        foreach ([
            'I promised to review their setup.',
            'I promised to review their setup.',
        ] as $content) {
            $entry = $add->execute(
                memory: $memory,
                type: BusinessMemoryEntryType::Note,
                content: $content,
                source: 'owner_context',
                confidence: 95
            );

            $extract->extract(
                $entry
            );
        }

        app(
            BusinessMemoryInsightService::class
        )->rebuild(
            $memory
        );

        $findings = app(
            CharlieFindingEngine::class
        )->findings(
            $client
        );

        $followUps = $findings
            ->where(
                'category',
                'follow_up'
            );

        $this->assertCount(
            1,
            $followUps
        );
    }
}
