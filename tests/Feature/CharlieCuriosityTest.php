<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\CheerfulCharlie\Curiosity\CharlieAnswerIngestionService;
use App\Domains\CheerfulCharlie\Curiosity\CharlieQuestionService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieCuriosityTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_asks_highest_value_unknown_and_learns_answer(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute($client);

        $questions = app(
            CharlieQuestionService::class
        );

        $first = $questions->next(
            $memory
        );

        $this->assertSame(
            'backup_provider',
            $first['key']
        );

        app(
            CharlieAnswerIngestionService::class
        )->ingest(
            memory: $memory,
            question: $first,
            answer: 'Backups are managed by Dave at XYZ IT.'
        );

        $second = $questions->next(
            $memory->fresh()
        );

        $this->assertNotSame(
            'backup_provider',
            $second['key']
        );

        $this->assertDatabaseHas(
            'business_contexts',
            [
                'business_memory_id' => $memory->id,

                'key' => 'backup_provider',

                'value' => 'Backups are managed by Dave at XYZ IT.',
            ]
        );
    }
}
