<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceAttributionCandidate;
use App\Models\Client;
use App\Models\ClientServiceAttributionReview;
use Illuminate\Support\Collection;

final class ClientServiceAttributionReviewQueueService
{
    public function __construct(
        private readonly ClientServiceAttributionCandidateService $candidates,
        private readonly ClientServiceAttributionEvidenceFingerprint $evidenceFingerprint,
    ) {}

    /**
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    public function ready(): Collection
    {
        return $this->unresolved(
            $this->candidates
                ->ready()
        );
    }

    /**
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    public function forClient(
        Client $client
    ): Collection {
        return $this->unresolved(
            $this->candidates
                ->forClient(
                    $client
                )
                ->filter(
                    fn (
                        ClientServiceAttributionCandidate $candidate
                    ) => $candidate
                        ->isReadyForReview()
                )
                ->values()
        );
    }

    private function unresolved(
        Collection $candidates
    ): Collection {
        return $candidates
            ->filter(
                fn (
                    ClientServiceAttributionCandidate $candidate
                ) => ! $this->resolved(
                    $candidate
                )
            )
            ->values();
    }

    private function resolved(
        ClientServiceAttributionCandidate $candidate
    ): bool {
        if (
            $candidate->clientServiceId
            === null
        ) {
            return false;
        }

        $evidenceFingerprint =
            $this->evidenceFingerprint
                ->forCandidate(
                    $candidate
                );

        $latest =
            ClientServiceAttributionReview::query()
                ->where(
                    'client_id',
                    $candidate->clientId
                )
                ->where(
                    'client_service_id',
                    $candidate
                        ->clientServiceId
                )
                ->where(
                    'candidate_fingerprint',
                    $candidate
                        ->candidateFingerprint
                )
                ->where(
                    'evidence_fingerprint',
                    $evidenceFingerprint
                )
                ->latest(
                    'reviewed_at'
                )
                ->latest(
                    'created_at'
                )
                ->first();

        if ($latest === null) {
            return false;
        }

        return in_array(
            $latest->decision,
            [
                'approved',
                'rejected',
            ],
            true
        );
    }
}
