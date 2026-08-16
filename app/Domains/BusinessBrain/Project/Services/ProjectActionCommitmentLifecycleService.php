<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectActionCommitment;
use Carbon\CarbonImmutable;

class ProjectActionCommitmentLifecycleService
{
    public function commit(ProjectActionCommitment $commitment): ProjectActionCommitment
    {
        $commitment->update([
            'status' => 'committed',
            'committed_at' => CarbonImmutable::now(),
        ]);

        return $commitment->fresh();
    }

    public function start(ProjectActionCommitment $commitment): ProjectActionCommitment
    {
        $commitment->update([
            'status' => 'in_progress',
        ]);

        return $commitment->fresh();
    }

    public function complete(ProjectActionCommitment $commitment): ProjectActionCommitment
    {
        $commitment->update([
            'status' => 'complete',
            'completed_at' => CarbonImmutable::now(),
        ]);

        return $commitment->fresh();
    }

    public function verify(ProjectActionCommitment $commitment): ProjectActionCommitment
    {
        $commitment->update([
            'status' => 'verified',
        ]);

        return $commitment->fresh();
    }
}
