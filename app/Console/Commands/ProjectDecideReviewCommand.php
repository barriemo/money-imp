<?php

namespace App\Console\Commands;

use App\Domains\Project\Decision\ProjectDecisionPresenter;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Domains\Project\Decision\ProjectDecisionService;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;
use Illuminate\Console\Command;

class ProjectDecideReviewCommand extends Command
{
    protected $signature =
        'project:decide-review
        {project_id : Exact positive project ID}';

    protected $description =
        'Assess whether one exact project should proceed to human project review';

    public function handle(
        ProjectDecisionService $service,
        ProjectDecisionPresenter $presenter
    ): int {
        $projectId =
            $this->requiredPositiveInteger(
                'project_id',
                'Project id'
            );

        if ($projectId === null) {
            return self::FAILURE;
        }

        $request =
            new ProjectDecisionRequest(
                key: ProjectReviewReadinessPolicy::KEY,

                question: 'Should this exact project proceed to human project review?',

                projectId: $projectId
            );

        $decision =
            $service
                ->decide(
                    $request
                );

        $this->line(
            $presenter->present(
                $decision
            )
        );

        return self::SUCCESS;
    }

    private function requiredPositiveInteger(
        string $argument,
        string $label
    ): ?int {
        $value =
            $this->argument(
                $argument
            );

        if (
            ! is_scalar($value)
            || ! ctype_digit(
                trim(
                    (string) $value
                )
            )
        ) {
            $this->error(
                $label
                .' must be a positive integer.'
            );

            return null;
        }

        $value =
            (int) trim(
                (string) $value
            );

        if ($value <= 0) {
            $this->error(
                $label
                .' must be a positive integer.'
            );

            return null;
        }

        return $value;
    }
}
