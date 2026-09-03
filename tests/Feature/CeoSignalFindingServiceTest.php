<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalFindingService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\ExecutiveAction;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalFindingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_supported_payment_candidate_becomes_useful_ceo_finding_without_becoming_truth(): void
    {
        [
            $entry,
            $case,
        ] = $this->fixture([
            'state' => 'no_supported_payment_candidate_found',

            'invoice_count' => 36,

            'accounting_outstanding' => 7218,

            'bank_date_span_covers_invoices' => true,

            'supported_candidate_count' => 0,

            'named_other_exact_amount_coincidence_count' => 199,

            'truth_boundary' => 'A payment evidence search can establish that no supported receipt candidate was found in the available evidence. It cannot prove that no payment occurred.',
        ]);

        $eventCountBefore =
            $case->events()
                ->count();

        $actionCountBefore =
            ExecutiveAction::count();

        $agreementCountBefore =
            CommercialAgreement::count();

        $coverageCountBefore =
            CommercialAgreementCoverageReview::count();

        $finding =
            app(
                CeoSignalFindingService::class
            )->forEntry(
                $entry->fresh()
            );

        $this->assertNotNull(
            $finding
        );

        $this->assertSame(
            'investigation_requires_attention',
            $finding->state
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $finding->searchState
        );

        $this->assertSame(
            'VF Electrical Services Ltd: £7,218.00 remains unresolved',
            $finding->headline
        );

        $this->assertStringContainsString(
            '36 invoices',
            $finding->summary
        );

        $this->assertStringContainsString(
            'no supported receipt candidate',
            $finding->summary
        );

        $this->assertStringContainsString(
            '199 same-amount bank transactions',
            $finding->summary
        );

        $this->assertStringContainsString(
            'other named payers',
            $finding->summary
        );

        $this->assertStringContainsString(
            'amount coincidence alone is not payment identity',
            $finding->summary
        );

        $this->assertStringNotContainsString(
            'did not pay',
            strtolower(
                $finding->summary
            )
        );

        $this->assertStringContainsString(
            'cannot prove that no payment occurred',
            $finding->truthBoundary
        );

        /*
         * Projection only:
         *
         * No investigation event, action, truth,
         * agreement or coverage decision may be written.
         */
        $this->assertSame(
            $eventCountBefore,
            $case->fresh()
                ->events()
                ->count()
        );

        $this->assertSame(
            $actionCountBefore,
            ExecutiveAction::count()
        );

        $this->assertSame(
            $agreementCountBefore,
            CommercialAgreement::count()
        );

        $this->assertSame(
            $coverageCountBefore,
            CommercialAgreementCoverageReview::count()
        );

        $this->assertSame(
            'open',
            $case->fresh()->status
        );

        $this->assertSame(
            0,
            $case->fresh()->confidence
        );

        $this->assertNull(
            $case->fresh()->verdict
        );

        $this->assertFalse(
            $entry->fresh()->verified
        );

        $this->assertSame(
            'unverified',
            $entry->fresh()
                ->metadata[
                    'truth_status'
                ]
        );
    }

    public function test_supported_candidate_is_presented_as_candidate_not_payment_truth(): void
    {
        [
            $entry,
        ] = $this->fixture([
            'state' => 'supported_payment_candidate_found',

            'invoice_count' => 3,

            'accounting_outstanding' => 1200,

            'bank_date_span_covers_invoices' => true,

            'supported_candidate_count' => 2,

            'truth_boundary' => 'Candidates require verification before any payment allocation or verdict.',
        ]);

        $finding =
            app(
                CeoSignalFindingService::class
            )->forEntry(
                $entry->fresh()
            );

        $this->assertNotNull(
            $finding
        );

        $this->assertSame(
            'candidate_requires_verification',
            $finding->state
        );

        $this->assertStringContainsString(
            'possible receipt evidence needs verification',
            $finding->headline
        );

        $this->assertStringContainsString(
            '2 bank transactions',
            $finding->summary
        );

        $this->assertStringContainsString(
            'no payment allocation or non-payment verdict has been created',
            $finding->summary
        );
    }

    public function test_missing_bank_evidence_blocks_payment_conclusion(): void
    {
        [
            $entry,
        ] = $this->fixture([
            'state' => 'bank_evidence_missing',

            'invoice_count' => 1,

            'accounting_outstanding' => 7218,

            'bank_date_span_covers_invoices' => false,

            'supported_candidate_count' => 0,
        ]);

        $finding =
            app(
                CeoSignalFindingService::class
            )->forEntry(
                $entry->fresh()
            );

        $this->assertNotNull(
            $finding
        );

        $this->assertSame(
            'evidence_missing',
            $finding->state
        );

        $this->assertSame(
            'bank_evidence_missing',
            $finding->searchState
        );

        $this->assertStringContainsString(
            'bank evidence is missing',
            $finding->headline
        );

        $this->assertStringContainsString(
            'cannot be treated as proof that payment was not received',
            $finding->summary
        );

        $this->assertStringContainsString(
            'No conclusion about payment presence or absence',
            $finding->truthBoundary
        );
    }

    public function test_incomplete_bank_coverage_blocks_negative_payment_inference(): void
    {
        [
            $entry,
        ] = $this->fixture([
            'state' => 'bank_date_span_incomplete',

            'invoice_count' => 4,

            'accounting_outstanding' => 2400,

            'bank_date_span_covers_invoices' => false,

            'supported_candidate_count' => 0,

            'truth_boundary' => 'Bank coverage must be complete before absence evidence can be interpreted.',
        ]);

        $finding =
            app(
                CeoSignalFindingService::class
            )->forEntry(
                $entry->fresh()
            );

        $this->assertNotNull(
            $finding
        );

        $this->assertSame(
            'evidence_coverage_incomplete',
            $finding->state
        );

        $this->assertFalse(
            $finding->bankDateSpanCoversInvoices
        );

        $this->assertStringContainsString(
            'does not span the full invoice period',
            $finding->summary
        );

        $this->assertStringContainsString(
            'cannot treat the absence',
            $finding->summary
        );
    }

    private function fixture(
        array $paymentPayload
    ): array {
        $user =
            User::factory()->create();

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: 'I want this client ledger position investigated.'
            );

        $cases =
            app(
                InvestigationCaseService::class
            );

        $ledgerCase =
            $cases->open(
                type: 'client_ledger',

                title: 'Investigate ledger anomaly for VF Electrical Services Ltd',

                question: 'Why does the client ledger not reconcile?',

                subjectType: 'client',

                subjectId: 'vf-client-id',

                subjectName: 'VF Electrical Services Ltd'
            );

        $metadata =
            $entry->metadata
            ?? [];

        $metadata[
            'routing'
        ] = [
            'status' => 'routed',

            'domain' => 'client_ledger',

            'subject_type' => 'client',

            'subject_id' => 'vf-client-id',

            'subject_name' => 'VF Electrical Services Ltd',

            'linked_investigation_case_id' => $ledgerCase->id,

            'invoice_count' => $paymentPayload[
                    'invoice_count'
                ]
                ?? 0,

            'accounting_outstanding' => $paymentPayload[
                    'accounting_outstanding'
                ]
                ?? 0,

            'canonical_cash' => 0,

            'truth_status' => 'unverified',
        ];

        $entry
            ->forceFill([
                'metadata' => $metadata,
            ])
            ->save();

        $cases->event(
            case: $ledgerCase,

            type: 'payment_evidence_search',

            description: 'Payment evidence search completed for VF Electrical Services Ltd. No payment allocation, client remap or verdict was created.',

            payload: array_merge(
                [
                    'business_memory_entry_id' => $entry->id,

                    'source' => 'client_payment_evidence_search',
                ],
                $paymentPayload
            )
        );

        return [
            $entry->fresh(),

            InvestigationCase::query()
                ->findOrFail(
                    $ledgerCase->id
                ),
        ];
    }
}
