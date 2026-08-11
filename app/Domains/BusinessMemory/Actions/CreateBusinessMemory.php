<?php

namespace App\Domains\BusinessMemory\Actions;

use App\Models\BusinessMemory;
use Illuminate\Database\Eloquent\Model;

class CreateBusinessMemory
{
    public function execute(
        Model $subject,
        ?string $title = null
    ): BusinessMemory {
        $resolvedTitle =
            $title
            ?? $subject->getAttribute('name')
            ?? class_basename($subject);

        return BusinessMemory::firstOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),

                'subject_id' => $subject->getKey(),
            ],
            [
                'title' => $resolvedTitle,

                'status' => 'active',
            ]
        );
    }
}
