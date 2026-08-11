<?php

namespace App\Domains\CheerfulCharlie\Daily;

use App\Domains\CheerfulCharlie\Review\CharlieFindingFingerprint;
use App\Models\CharlieReview;

class CharlieReviewDeltaService
{
    public function __construct(
        private CharlieFindingFingerprint $fingerprints
    ) {}

    public function compare(
        CharlieReview $previous,
        CharlieReview $current
    ): array {
        $previous->loadMissing(
            'findings'
        );

        $current->loadMissing(
            'findings'
        );

        $old = $previous
            ->findings
            ->keyBy(
                fn ($finding) => $this->fingerprints
                    ->make(
                        $finding->toArray()
                    )
            );

        $new = $current
            ->findings
            ->keyBy(
                fn ($finding) => $this->fingerprints
                    ->make(
                        $finding->toArray()
                    )
            );

        $newFindings = $new
            ->reject(
                fn ($finding, $key) => $old->has($key)
            )
            ->values();

        $resolved = $old
            ->reject(
                fn ($finding, $key) => $new->has($key)
            )
            ->values();

        $unchanged = $new
            ->filter(
                fn ($finding, $key) => $old->has($key)
            )
            ->values();

        return [
            'new' => $newFindings,

            'resolved' => $resolved,

            'unchanged' => $unchanged,

            'new_count' => $newFindings->count(),

            'resolved_count' => $resolved->count(),

            'unchanged_count' => $unchanged->count(),
        ];
    }
}
