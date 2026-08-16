<?php

namespace App\Domains\BusinessBrain\Insights;

class BusinessInsightBuilder
{
    private string $headline = '';

    private string $status = 'informational';

    private string $summary = '';

    private array $metrics = [];

    private array $risks = [];

    private array $actions = [];

    private int $confidence = 0;

    public function headline(
        string $headline
    ): self {
        $this->headline = $headline;

        return $this;
    }

    public function status(
        string $status
    ): self {
        $this->status = $status;

        return $this;
    }

    public function summary(
        string $summary
    ): self {
        $this->summary = $summary;

        return $this;
    }

    public function metric(
        string $key,
        mixed $value
    ): self {
        $this->metrics[$key] = $value;

        return $this;
    }

    public function risk(
        string $risk
    ): self {
        $this->risks[] = $risk;

        return $this;
    }

    public function action(
        string $action
    ): self {
        $this->actions[] = $action;

        return $this;
    }

    public function confidence(
        int $confidence
    ): self {
        $this->confidence = max(
            0,
            min(
                100,
                $confidence
            )
        );

        return $this;
    }

    public function build(): BusinessInsight
    {
        return new BusinessInsight(
            headline: $this->headline,

            status: $this->status,

            summary: $this->summary,

            metrics: $this->metrics,

            risks: $this->risks,

            actions: $this->actions,

            confidence: $this->confidence
        );
    }
}
