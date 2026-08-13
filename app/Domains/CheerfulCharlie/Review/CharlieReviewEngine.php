<?php

namespace App\Domains\CheerfulCharlie\Review;

use App\Models\CharlieReview;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class CharlieReviewEngine
{
    public function __construct(
        private CharlieFindingEngine $findings,

        private CharlieFindingFingerprint $fingerprints
    ) {}

    public function review(
        Client $client
    ): CharlieReview {
        return DB::transaction(
            function () use (
                $client
            ): CharlieReview {
                $previous =
                    CharlieReview::query()
                        ->where(
                            'client_id',
                            $client->id
                        )
                        ->latest(
                            'reviewed_at'
                        )
                        ->first();

                $items =
                    $this->findings
                        ->findings(
                            $client
                        );

                $review =
                    CharlieReview::create([
                        'client_id' => $client->id,

                        'reviewed_at' => now(),

                        'finding_count' => $items->count(),

                        'high_priority_count' => $items
                            ->whereIn(
                                'severity',
                                [
                                    'critical',
                                    'high',
                                ]
                            )
                            ->count(),

                        'status' => 'complete',
                    ]);

                foreach ($items as $item) {
                    $review
                        ->findings()
                        ->create([
                            'client_id' => $client->id,

                            ...$item,
                        ]);
                }

                $review->load(
                    'findings'
                );

                if ($previous) {
                    $this->finalisePreviousFindings(
                        $previous,
                        $review
                    );
                }

                return $review;
            }
        );
    }

    private function finalisePreviousFindings(
        CharlieReview $previous,
        CharlieReview $current
    ): void {
        $previous->loadMissing(
            'findings'
        );

        $current->loadMissing(
            'findings'
        );

        $currentFingerprints =
            $current
                ->findings
                ->mapWithKeys(
                    fn ($finding) => [
                        $this->fingerprints
                            ->make(
                                $finding->toArray()
                            ) => true,
                    ]
                );

        foreach (
            $previous->findings as $finding
        ) {
            $fingerprint =
                $this->fingerprints
                    ->make(
                        $finding->toArray()
                    );

            $finding->update([
                'status' => $currentFingerprints
                    ->has(
                        $fingerprint
                    )
                        ? 'superseded'
                        : 'resolved',
            ]);
        }
    }
}
