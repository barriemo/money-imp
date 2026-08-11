<?php

namespace App\Domains\CheerfulCharlie\Review;

use App\Models\CharlieReview;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class CharlieReviewEngine
{
    public function __construct(
        private CharlieFindingEngine $findings
    ) {}

    public function review(
        Client $client
    ): CharlieReview {
        return DB::transaction(
            function () use (
                $client
            ): CharlieReview {
                $items =
                    $this->findings
                        ->findings($client);

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

                return $review->load(
                    'findings'
                );
            }
        );
    }
}
