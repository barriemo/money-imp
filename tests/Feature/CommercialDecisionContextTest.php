<?php

namespace Tests\Feature;

use App\Domains\Commercial\Decision\CommercialDecisionContext;
use App\Domains\Commercial\Decision\CommercialDecisionContextService;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidate;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\DTO\CurrentCommercialPosition;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateEvidenceFingerprint;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

class CommercialDecisionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_inert_validated_exact_subject_input(): void
    {
        $request =
            new CommercialDecisionRequest(
                key: 'commercial_subject',
                question: 'What commercial truth is available for this subject?',
                clientId: 'client-123',
                candidateFingerprint: 'candidate-456',
                evidenceFingerprint: 'evidence-789',
                parameters: [
                    'note' => 'context only',
                    'attempt' => 1,
                    'optional' => null,
                ]
            );

        $this->assertSame(
            'commercial_subject',
            $request->key
        );

        $this->assertSame(
            'client-123',
            $request->clientId
        );

        $this->assertSame(
            'candidate-456',
            $request->candidateFingerprint
        );

        $this->assertSame(
            'evidence-789',
            $request->evidenceFingerprint
        );

        $this->assertTrue(
            $request->hasClientSubject()
        );

        $this->assertTrue(
            $request->hasCandidateSubject()
        );
    }

    public function test_request_rejects_incomplete_or_invalid_exact_subject_identity(): void
    {
        $cases = [
            fn () => new CommercialDecisionRequest(
                key: '',
                question: 'Question.'
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: ''
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: ''
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                candidateFingerprint: ''
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                evidenceFingerprint: ''
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                candidateFingerprint: 'candidate-without-client',
                evidenceFingerprint: 'evidence'
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                evidenceFingerprint: 'evidence-without-candidate'
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                clientId: 'client-1',
                candidateFingerprint: 'candidate-without-evidence'
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                parameters: [
                    '' => 1,
                ]
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                parameters: [
                    'object' => new stdClass,
                ]
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                parameters: [
                    'nested' => [
                        'not' => 'allowed',
                    ],
                ]
            ),

            fn () => new CommercialDecisionRequest(
                key: 'key',
                question: 'Question.',
                parameters: [
                    'amount' => INF,
                ]
            ),
        ];

        foreach ($cases as $case) {
            try {
                $case();

                $this->fail(
                    'Expected invalid commercial decision request to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_aggregate_context_contains_one_aligned_commercial_observation(): void
    {
        $context =
            new CommercialDecisionContext(
                request: $this->request(),
                position: $this->position('2026-09-04'),
                candidate: null,
                candidateEvidenceFingerprint: null,
                candidateInReconciliationQueue: null,
                asOf: CarbonImmutable::parse(
                    '2026-09-04 20:45:00'
                )
            );

        $this->assertFalse(
            $context->hasCandidateSubject()
        );

        $this->assertNull(
            $context->candidateEvidenceFingerprint
        );
    }

    public function test_candidate_context_requires_exact_three_part_subject_identity(): void
    {
        $evidenceFingerprint =
            $this->evidenceFingerprint();

        $context =
            new CommercialDecisionContext(
                request: $this->request(
                    clientId: 'client-1',
                    candidateFingerprint: 'fingerprint-1',
                    evidenceFingerprint: $evidenceFingerprint
                ),
                position: $this->position(
                    '2026-09-04'
                ),
                candidate: $this->assessment(
                    clientId: 'client-1',
                    fingerprint: 'fingerprint-1'
                ),
                candidateEvidenceFingerprint: $evidenceFingerprint,
                candidateInReconciliationQueue: true,
                asOf: CarbonImmutable::parse(
                    '2026-09-04 20:45:00'
                )
            );

        $this->assertTrue(
            $context->hasCandidateSubject()
        );

        $this->assertSame(
            $evidenceFingerprint,
            $context->candidateEvidenceFingerprint
        );

        $this->assertTrue(
            $context->candidateInReconciliationQueue
        );
    }

    public function test_context_rejects_misaligned_position_date(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new CommercialDecisionContext(
            request: $this->request(),
            position: $this->position(
                '2026-09-03'
            ),
            candidate: null,
            candidateEvidenceFingerprint: null,
            candidateInReconciliationQueue: null,
            asOf: CarbonImmutable::parse(
                '2026-09-04'
            )
        );
    }

    public function test_context_rejects_candidate_truth_without_exact_candidate_subject(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new CommercialDecisionContext(
            request: $this->request(
                clientId: 'client-1'
            ),
            position: $this->position(
                '2026-09-04'
            ),
            candidate: $this->assessment(),
            candidateEvidenceFingerprint: $this->evidenceFingerprint(),
            candidateInReconciliationQueue: true,
            asOf: CarbonImmutable::parse(
                '2026-09-04'
            )
        );
    }

    public function test_candidate_context_requires_candidate_evidence_identity_and_queue_state(): void
    {
        $request =
            $this->request(
                clientId: 'client-1',
                candidateFingerprint: 'fingerprint-1'
            );

        $cases = [
            [
                'candidate' => null,
                'evidence' => $this->evidenceFingerprint(),
                'queue' => true,
            ],
            [
                'candidate' => $this->assessment(),
                'evidence' => null,
                'queue' => true,
            ],
            [
                'candidate' => $this->assessment(),
                'evidence' => $this->evidenceFingerprint(),
                'queue' => null,
            ],
        ];

        foreach ($cases as $case) {
            try {
                new CommercialDecisionContext(
                    request: $request,
                    position: $this->position(
                        '2026-09-04'
                    ),
                    candidate: $case['candidate'],
                    candidateEvidenceFingerprint: $case['evidence'],
                    candidateInReconciliationQueue: $case['queue'],
                    asOf: CarbonImmutable::parse(
                        '2026-09-04'
                    )
                );

                $this->fail(
                    'Expected incomplete exact candidate context to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_candidate_context_rejects_identity_and_date_mismatches(): void
    {
        $request =
            $this->request(
                clientId: 'client-1',
                candidateFingerprint: 'fingerprint-1'
            );

        $cases = [
            [
                'candidate' => $this->assessment(
                    clientId: 'other-client'
                ),
                'evidence' => $this->evidenceFingerprint(),
            ],
            [
                'candidate' => $this->assessment(
                    fingerprint: 'other-fingerprint'
                ),
                'evidence' => $this->evidenceFingerprint(),
            ],
            [
                'candidate' => $this->assessment(),
                'evidence' => 'wrong-evidence-fingerprint',
            ],
            [
                'candidate' => $this->assessment(
                    asOfDate: '2026-09-03'
                ),
                'evidence' => $this->evidenceFingerprint(),
            ],
        ];

        foreach ($cases as $case) {
            try {
                new CommercialDecisionContext(
                    request: $request,
                    position: $this->position(
                        '2026-09-04'
                    ),
                    candidate: $case['candidate'],
                    candidateEvidenceFingerprint: $case['evidence'],
                    candidateInReconciliationQueue: false,
                    asOf: CarbonImmutable::parse(
                        '2026-09-04'
                    )
                );

                $this->fail(
                    'Expected exact candidate identity mismatch to fail.'
                );
            } catch (InvalidArgumentException) {
                $this->assertTrue(
                    true
                );
            }
        }
    }

    public function test_context_rejects_queue_presence_for_non_review_ready_candidate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new CommercialDecisionContext(
            request: $this->request(
                clientId: 'client-1',
                candidateFingerprint: 'fingerprint-1'
            ),
            position: $this->position(
                '2026-09-04'
            ),
            candidate: $this->assessment(
                promotionReadiness: 'needs_more_evidence'
            ),
            candidateEvidenceFingerprint: $this->evidenceFingerprint(),
            candidateInReconciliationQueue: true,
            asOf: CarbonImmutable::parse(
                '2026-09-04'
            )
        );
    }

    public function test_context_service_builds_aggregate_context_without_inventing_subject_truth(): void
    {
        $context =
            app(
                CommercialDecisionContextService::class
            )->forDecision(
                request: $this->request(),
                asOf: CarbonImmutable::parse(
                    '2026-09-04'
                )
            );

        $this->assertSame(
            '2026-09-04',
            $context->position->asOfDate
        );

        $this->assertNull(
            $context->candidate
        );

        $this->assertNull(
            $context->candidateEvidenceFingerprint
        );

        $this->assertNull(
            $context->candidateInReconciliationQueue
        );
    }

    public function test_context_service_validates_client_subject_without_creating_candidate_truth(): void
    {
        $client =
            Client::query()
                ->create([
                    'name' => 'Context Client',
                    'status' => 'active',
                    'currency' => 'GBP',
                ]);

        $context =
            app(
                CommercialDecisionContextService::class
            )->forDecision(
                request: $this->request(
                    clientId: (string) $client->id
                ),
                asOf: CarbonImmutable::parse(
                    '2026-09-04'
                )
            );

        $this->assertTrue(
            $context
                ->request
                ->hasClientSubject()
        );

        $this->assertFalse(
            $context
                ->request
                ->hasCandidateSubject()
        );

        $this->assertNull(
            $context->candidate
        );
    }

    public function test_context_service_rejects_unknown_client_and_unknown_exact_candidate(): void
    {
        try {
            app(
                CommercialDecisionContextService::class
            )->forDecision(
                request: $this->request(
                    clientId: 'missing-client'
                ),
                asOf: CarbonImmutable::parse(
                    '2026-09-04'
                )
            );

            $this->fail(
                'Expected unknown commercial client to fail.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Commercial decision subject client does not exist.',
                $exception->getMessage()
            );
        }

        $client =
            Client::query()
                ->create([
                    'name' => 'Candidate Context Client',
                    'status' => 'active',
                    'currency' => 'GBP',
                ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Commercial decision exact candidate does not exist in current commercial truth.'
        );

        app(
            CommercialDecisionContextService::class
        )->forDecision(
            request: $this->request(
                clientId: (string) $client->id,
                candidateFingerprint: 'missing-fingerprint',
                evidenceFingerprint: 'missing-evidence'
            ),
            asOf: CarbonImmutable::parse(
                '2026-09-04'
            )
        );
    }

    public function test_upstream_evidence_fingerprint_distinguishes_exact_source_sets(): void
    {
        $first =
            $this->assessment(
                invoiceItemIds: [
                    'invoice-item-1',
                    'invoice-item-2',
                ]
            );

        $second =
            $this->assessment(
                invoiceItemIds: [
                    'invoice-item-3',
                ]
            );

        $fingerprinter =
            app(
                ClientServiceCandidateEvidenceFingerprint::class
            );

        $this->assertSame(
            'fingerprint-1',
            $first->candidate->fingerprint
        );

        $this->assertSame(
            'fingerprint-1',
            $second->candidate->fingerprint
        );

        $this->assertNotSame(
            $fingerprinter->forCandidate(
                $first->candidate
            ),
            $fingerprinter->forCandidate(
                $second->candidate
            )
        );
    }

    public function test_context_contract_contains_no_recommendation_confidence_priority_ranking_or_execution_fields(): void
    {
        $reflection =
            new ReflectionClass(
                CommercialDecisionContext::class
            );

        foreach (
            [
                'recommendation',
                'confidence',
                'priority',
                'score',
                'urgency',
                'ranking',
                'action',
                'execution',
                'executedAt',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    private function request(
        ?string $clientId = null,
        ?string $candidateFingerprint = null,
        ?string $evidenceFingerprint = null
    ): CommercialDecisionRequest {
        if (
            $candidateFingerprint !== null
            && $evidenceFingerprint === null
        ) {
            $evidenceFingerprint =
                $this->evidenceFingerprint();
        }

        return new CommercialDecisionRequest(
            key: 'commercial_context_test',
            question: 'What commercial truth is available for this requested subject?',
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            evidenceFingerprint: $evidenceFingerprint
        );
    }

    private function position(
        string $asOfDate
    ): CurrentCommercialPosition {
        return new CurrentCommercialPosition(
            asOfDate: $asOfDate,
            serviceCandidateCount: 0,
            recurringCandidateCount: 0,
            currentRecurringCandidateCount: 0,
            supportedCurrentMonthlyEquivalent: 0.0,
            recentlyObservedRecurringCandidateCount: 0,
            recentlyObservedMonthlyEquivalent: 0.0,
            staleRecurringCandidateCount: 0,
            staleMonthlyEquivalent: 0.0,
            historicalRecurringCandidateCount: 0,
            historicalMonthlyEquivalent: 0.0,
            readyForReviewCount: 0,
            needsMoreEvidenceCount: 0,
            sourceEvidenceItemCount: 0,
            currentEvidenceItemCount: 0,
            byServiceType: [],
            byClient: [],
            evidenceStatus: 'no_current_recurring_evidence',
            caveats: [],
            provenance: []
        );
    }

    private function assessment(
        string $clientId = 'client-1',
        string $fingerprint = 'fingerprint-1',
        string $asOfDate = '2026-09-04',
        string $promotionReadiness = 'ready_for_review',
        array $invoiceItemIds = [
            'invoice-item-1',
            'invoice-item-2',
        ]
    ): ClientServiceCandidateAssessment {
        return new ClientServiceCandidateAssessment(
            candidate: new ClientServiceCandidate(
                clientId: $clientId,
                clientName: 'Example Client',
                serviceType: 'seo',
                serviceHint: null,
                fingerprint: $fingerprint,
                commercialTreatment: 'service_candidate',
                evidenceCount: count(
                    $invoiceItemIds
                ),
                invoiceItemIds: $invoiceItemIds,
                signedObservedNet: 1000.0,
                positiveObservedNet: 1000.0,
                negativeObservedNet: 0.0,
                latestObservedUnitPrice: 500.0,
                firstObservedOn: '2026-07-01',
                lastObservedOn: '2026-09-01',
                cadence: 'monthly',
                monthlyEquivalent: 500.0,
                classificationConfidence: 100,
                cadenceConfidence: 100
            ),
            asOfDate: $asOfDate,
            daysSinceLastObservation: 3,
            freshness: 'current',
            cadenceEstablished: true,
            recurringEvidence: true,
            currentMonthlyEquivalent: 500.0,
            promotionReadiness: $promotionReadiness,
            reasons: [
                'Test commercial evidence.',
            ]
        );
    }

    private function evidenceFingerprint(): string
    {
        $ids = [
            'invoice-item-1',
            'invoice-item-2',
        ];

        sort(
            $ids,
            SORT_STRING
        );

        return hash(
            'sha256',
            implode(
                '|',
                $ids
            )
        );
    }
}
