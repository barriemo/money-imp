<?php

namespace App\Http\Controllers;

use App\Domains\CommercialTruth\DTO\ClientServiceAttributionCandidate;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Models\AccountingInvoiceItem;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CommercialReconciliationController extends Controller
{
    public function index(
        Request $request,
        ClientServiceReconciliationQueueService $serviceQueue,
        ClientServiceAttributionReviewQueueService $attributionQueue,
    ): View {
        $queue =
            (string) $request->query(
                'queue',
                'services'
            );

        if (
            ! in_array(
                $queue,
                [
                    'services',
                    'attribution',
                ],
                true
            )
        ) {
            $queue =
                'services';
        }

        $asOf =
            CarbonImmutable::today();

        $serviceCandidates =
            $this->sortServiceCandidates(
                $serviceQueue
                    ->ready(
                        $asOf
                    )
            );

        $attributionCandidates =
            $this->sortAttributionCandidates(
                $attributionQueue
                    ->ready()
            );

        $existingServices =
            ClientService::query()
                ->where(
                    'status',
                    'active'
                )
                ->orderBy(
                    'name'
                )
                ->get()
                ->groupBy(
                    fn (
                        ClientService $service
                    ) => (string) $service
                        ->client_id
                );

        return view(
            'reconciliation.commercial',
            [
                'queue' => $queue,

                'asOf' => $asOf,

                'serviceCandidates' => $serviceCandidates,

                'attributionCandidates' => $attributionCandidates,

                'serviceEvidence' => $this->serviceEvidence(
                    $serviceCandidates
                ),

                'attributionEvidence' => $this->attributionEvidence(
                    $attributionCandidates
                ),

                'existingServices' => $existingServices,

                'counts' => [
                'services' => $serviceCandidates
                    ->count(),

                'attribution' => $attributionCandidates
                    ->count(),
                ],
            ]
        );
    }

    public function confirmService(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceReconciliationService $reconciliation,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'service_name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $review =
            $reconciliation
                ->confirm(
                    clientId: $clientId,
                    candidateFingerprint: $candidateFingerprint,
                    serviceName: $validated[
                            'service_name'
                        ],
                    reviewedBy: $request->user()->id,
                    reason: $validated[
                            'reason'
                        ] ?? null,
                    asOf: CarbonImmutable::today(),
                );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'services',
                ]
            )
            ->with(
                'success',
                'Canonical client service '
                .$review
                    ->clientService
                    ->name
                .' confirmed from the reviewed evidence.'
            );
    }

    public function mergeService(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceReconciliationService $reconciliation,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'client_service_id' => [
                    'required',
                    'uuid',
                    'exists:client_services,id',
                ],
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $review =
            $reconciliation
                ->merge(
                    clientId: $clientId,
                    candidateFingerprint: $candidateFingerprint,
                    clientServiceId: $validated[
                            'client_service_id'
                        ],
                    reviewedBy: $request->user()->id,
                    reason: $validated[
                            'reason'
                        ] ?? null,
                    asOf: CarbonImmutable::today(),
                );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'services',
                ]
            )
            ->with(
                'success',
                'Reviewed billing evidence merged into '
                .$review
                    ->clientService
                    ->name
                .'.'
            );
    }

    public function deferService(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceReconciliationService $reconciliation,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $reconciliation
            ->defer(
                clientId: $clientId,
                candidateFingerprint: $candidateFingerprint,
                reviewedBy: $request->user()->id,
                reason: $validated[
                        'reason'
                    ] ?? null,
                asOf: CarbonImmutable::today(),
            );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'services',
                ]
            )
            ->with(
                'success',
                'Candidate deferred. It remains available for future human review.'
            );
    }

    public function rejectService(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceReconciliationService $reconciliation,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $reconciliation
            ->reject(
                clientId: $clientId,
                candidateFingerprint: $candidateFingerprint,
                reviewedBy: $request->user()->id,
                reason: $validated[
                        'reason'
                    ] ?? null,
                asOf: CarbonImmutable::today(),
            );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'services',
                ]
            )
            ->with(
                'success',
                'Exact reviewed evidence rejected as a canonical client service.'
            );
    }

    public function approveAttribution(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceAttributionReviewService $review,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $result =
            $review->approve(
                clientId: $clientId,
                candidateFingerprint: $candidateFingerprint,
                reviewedBy: $request->user()->id,
                reason: $validated[
                        'reason'
                    ] ?? null,
            );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'attribution',
                ]
            )
            ->with(
                'success',
                'Invoice evidence attributed to '
                .$result
                    ->clientService
                    ->name
                .'.'
            );
    }

    public function rejectAttribution(
        Request $request,
        string $clientId,
        string $candidateFingerprint,
        ClientServiceAttributionReviewService $review,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'reason' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $review->reject(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            reviewedBy: $request->user()->id,
            reason: $validated[
                    'reason'
                ] ?? null,
        );

        return redirect()
            ->route(
                'reconciliation.commercial.index',
                [
                    'queue' => 'attribution',
                ]
            )
            ->with(
                'success',
                'Exact attribution evidence rejected.'
            );
    }

    /**
     * @param  Collection<int, ClientServiceCandidateAssessment>  $rows
     * @return Collection<int, ClientServiceCandidateAssessment>
     */
    private function sortServiceCandidates(
        Collection $rows
    ): Collection {
        $freshnessRank = [
            'current' => 0,
            'recently_observed' => 1,
            'stale' => 2,
            'historical' => 3,
            'unknown' => 4,
        ];

        return $rows
            ->sort(
                function (
                    ClientServiceCandidateAssessment $left,
                    ClientServiceCandidateAssessment $right
                ) use (
                    $freshnessRank
                ): int {
                    $leftRank =
                        $freshnessRank[
                            $left->freshness
                        ] ?? 9;

                    $rightRank =
                        $freshnessRank[
                            $right->freshness
                        ] ?? 9;

                    if (
                        $leftRank
                        !== $rightRank
                    ) {
                        return $leftRank
                            <=> $rightRank;
                    }

                    $leftValue =
                        $left
                            ->currentMonthlyEquivalent
                        ?? $left
                            ->candidate
                            ->monthlyEquivalent;

                    $rightValue =
                        $right
                            ->currentMonthlyEquivalent
                        ?? $right
                            ->candidate
                            ->monthlyEquivalent;

                    if (
                        (float) $leftValue
                        !== (float) $rightValue
                    ) {
                        return (float) $rightValue
                            <=>
                            (float) $leftValue;
                    }

                    $client =
                        strcasecmp(
                            $left
                                ->candidate
                                ->clientName,
                            $right
                                ->candidate
                                ->clientName
                        );

                    if ($client !== 0) {
                        return $client;
                    }

                    return strcmp(
                        $left
                            ->candidate
                            ->fingerprint,
                        $right
                            ->candidate
                            ->fingerprint
                    );
                }
            )
            ->values();
    }

    /**
     * @param  Collection<int, ClientServiceAttributionCandidate>  $rows
     * @return Collection<int, ClientServiceAttributionCandidate>
     */
    private function sortAttributionCandidates(
        Collection $rows
    ): Collection {
        return $rows
            ->sort(
                function (
                    ClientServiceAttributionCandidate $left,
                    ClientServiceAttributionCandidate $right
                ): int {
                    $value =
                        abs(
                            $right
                                ->signedObservedNet
                        )
                        <=>
                        abs(
                            $left
                                ->signedObservedNet
                        );

                    if ($value !== 0) {
                        return $value;
                    }

                    return strcasecmp(
                        $left
                            ->clientName,
                        $right
                            ->clientName
                    );
                }
            )
            ->values();
    }

    /**
     * @param  Collection<int, ClientServiceCandidateAssessment>  $rows
     */
    private function serviceEvidence(
        Collection $rows
    ): Collection {
        $ids =
            $rows
                ->flatMap(
                    fn (
                        ClientServiceCandidateAssessment $row
                    ) => $row
                        ->candidate
                        ->invoiceItemIds
                )
                ->unique()
                ->values();

        return $this->evidence(
            $ids
        );
    }

    /**
     * @param  Collection<int, ClientServiceAttributionCandidate>  $rows
     */
    private function attributionEvidence(
        Collection $rows
    ): Collection {
        $ids =
            $rows
                ->flatMap(
                    fn (
                        ClientServiceAttributionCandidate $row
                    ) => $row
                        ->invoiceItemIds
                )
                ->unique()
                ->values();

        return $this->evidence(
            $ids
        );
    }

    private function evidence(
        Collection $ids
    ): Collection {
        if ($ids->isEmpty()) {
            return collect();
        }

        return AccountingInvoiceItem::query()
            ->with(
                'invoice'
            )
            ->whereIn(
                'id',
                $ids->all()
            )
            ->get()
            ->keyBy(
                fn (
                    AccountingInvoiceItem $item
                ) => (string) $item->id
            );
    }
}
