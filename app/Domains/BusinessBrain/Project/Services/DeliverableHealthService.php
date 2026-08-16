<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Delivery\DeliverableHealth;
use App\Models\ProjectDeliverable;
use Carbon\Carbon;

class DeliverableHealthService
{
    public function assess(
        ProjectDeliverable $deliverable
    ): DeliverableHealth {
        if (
            $deliverable->status === 'completed'
        ) {
            return new DeliverableHealth(
                'complete',
                'Deliverable completed.'
            );
        }

        if (
            $deliverable->due_date
            && Carbon::parse(
                $deliverable->due_date
            )->isPast()
        ) {
            return new DeliverableHealth(
                'overdue',
                'Deliverable has passed its agreed due date.'
            );
        }

        return new DeliverableHealth(
            'on_track',
            'Deliverable is within agreed timeline.'
        );
    }
}
