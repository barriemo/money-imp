<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionCommitmentLifecycleService;
use App\Models\ProjectActionCommitment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionCommitmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_commitment_can_be_committed(): void
    {
        $commitment = ProjectActionCommitment::factory()->create([
            'status' => 'open',
        ]);

        $result = app(
            ProjectActionCommitmentLifecycleService::class
        )->commit($commitment);

        $this->assertSame(
            'committed',
            $result->status
        );

        $this->assertNotNull(
            $result->committed_at
        );
    }

    public function test_commitment_can_be_completed(): void
    {
        $commitment = ProjectActionCommitment::factory()->create();

        $result = app(
            ProjectActionCommitmentLifecycleService::class
        )->complete($commitment);

        $this->assertSame(
            'complete',
            $result->status
        );

        $this->assertNotNull(
            $result->completed_at
        );
    }

    public function test_commitment_can_be_verified(): void
    {
        $commitment = ProjectActionCommitment::factory()->create([
            'status' => 'complete',
        ]);

        $result = app(
            ProjectActionCommitmentLifecycleService::class
        )->verify($commitment);

        $this->assertSame(
            'verified',
            $result->status
        );
    }
}
