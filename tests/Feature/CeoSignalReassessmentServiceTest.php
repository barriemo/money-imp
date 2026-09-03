<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Domains\BusinessBrain\Signals\CeoSignalReassessmentService;
use App\Domains\BusinessBrain\Signals\CeoSignalRoutingService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BusinessMemoryEntry;
use App\Models\Client;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\ExecutiveAction;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalReassessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_payment_event_without_stage_4a_fields_does_not_change_only_because_schema_was_enriched(): void
    {
        [
            $entry,
            $humanCase,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        $search =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole();

        /*
         * Simulate the exact shape of a payment-search event
         * persisted before Stage 4A existed.
         */
        $payload =
            $search->payload;

        $stage4aKeys = [
            'confirmed_allocated_payment',
            'allocation_uncovered_amount',
            'approved_payment_count',
            'source_outstanding_disagreement_count',
        ];

        foreach ($stage4aKeys as $key) {
            unset(
                $payload[
                    $key
                ]
            );
        }

        $search
            ->forceFill([
                'payload' => $payload,
            ])
            ->save();

        $search->refresh();

        foreach ($stage4aKeys as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $search->payload
            );
        }

        $beforeCount =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count();

        $result =
            app(
                CeoSignalReassessmentService::class
            )->reassess(
                $entry->fresh()
            );

        /*
         * The current search now contains the new Stage 4A
         * fields, but their zero/default values describe the
         * same evidence as the historical event.
         *
         * Deployment/schema enrichment alone must therefore
         * not create a fake evidence-change event.
         */
        $this->assertFalse(
            $result->changed
        );

        $this->assertSame(
            'unchanged',
            $result->status
        );

        $this->assertSame(
            'bank_evidence_missing',
            $result->previousState
        );

        $this->assertSame(
            'bank_evidence_missing',
            $result->currentState
        );

        $this->assertSame(
            $beforeCount,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count()
        );

        $this->assertTruthStillUnverified(
            entryId: $entry->id,

            humanCaseId: $humanCase->id,

            ledgerCaseId: $ledgerCase->id
        );
    }

    public function test_unchanged_evidence_does_not_append_reassessment_event(): void
    {
        [
            $entry,
            $humanCase,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        $this->assertSame(
            1,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->count()
        );

        $beforeCount =
            $ledgerCase
                ->events()
                ->count();

        $result =
            app(
                CeoSignalReassessmentService::class
            )->reassess(
                $entry->fresh()
            );

        $this->assertFalse(
            $result->changed
        );

        $this->assertSame(
            'unchanged',
            $result->status
        );

        $this->assertSame(
            'bank_evidence_missing',
            $result->previousState
        );

        $this->assertSame(
            'bank_evidence_missing',
            $result->currentState
        );

        $this->assertSame(
            0,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count()
        );

        $this->assertSame(
            $beforeCount,
            $ledgerCase
                ->events()
                ->count()
        );

        $this->assertTruthStillUnverified(
            entryId: $entry->id,

            humanCaseId: $humanCase->id,

            ledgerCaseId: $ledgerCase->id
        );
    }

    public function test_new_supported_bank_evidence_evolves_current_answer_once_without_creating_truth(): void
    {
        [
            $entry,
            $humanCase,
            $ledgerCase,
            $client,
        ] =
            $this->captureVfSignal();

        $beforeActions =
            ExecutiveAction::count();

        $beforeAgreements =
            CommercialAgreement::count();

        $beforeCoverage =
            CommercialAgreementCoverageReview::count();

        $account =
            BankAccount::factory()
                ->create([
                    'name' => 'Current Account',

                    'currency' => 'GBP',
                ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,

            'transaction_date' => '2026-07-30',

            'amount' => 7218,

            'currency' => 'GBP',

            'description' => 'VF ELECTRICAL SERVICES LTD',

            'transaction_type' => 'imported',

            'match_status' => 'unmatched',

            'source_type' => 'freeagent',

            'transaction_hash' => hash(
                'sha256',
                'vf-reassessment-supported-payment'
            ),

            'metadata' => [
                'freeagent_full_description' => 'VF ELECTRICAL SERVICES LTD',

                'freeagent_unexplained_amount' => 7218,
            ],
        ]);

        $result =
            app(
                CeoSignalReassessmentService::class
            )->reassess(
                $entry->fresh()
            );

        $this->assertTrue(
            $result->changed
        );

        $this->assertSame(
            'changed',
            $result->status
        );

        $this->assertSame(
            'bank_evidence_missing',
            $result->previousState
        );

        $this->assertSame(
            'supported_payment_candidate_found',
            $result->currentState
        );

        $reassessment =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        $this->assertSame(
            $entry->id,
            $reassessment->payload[
                'business_memory_entry_id'
            ]
        );

        $this->assertSame(
            'bank_evidence_missing',
            $reassessment->payload[
                'previous_state'
            ]
        );

        $this->assertSame(
            'supported_payment_candidate_found',
            $reassessment->payload[
                'state'
            ]
        );

        $this->assertSame(
            1,
            $reassessment->payload[
                'supported_candidate_count'
            ]
        );

        $answer =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $entry->metadata[
                        'submitted_by_user_id'
                    ]
            )
                ->sole();

        $this->assertSame(
            'candidate_requires_verification',
            $answer->status
        );

        $this->assertSame(
            'Evidence to verify',
            $answer->statusLabel
        );

        $this->assertSame(
            'VF Electrical Services Ltd: possible receipt evidence needs verification',
            $answer->headline
        );

        $this->assertStringContainsString(
            'no payment allocation or non-payment verdict has been created',
            $answer->summary
        );

        /*
         * Running the same reassessment again must not
         * create another event.
         */
        $second =
            app(
                CeoSignalReassessmentService::class
            )->reassess(
                $entry->fresh()
            );

        $this->assertFalse(
            $second->changed
        );

        $this->assertSame(
            'unchanged',
            $second->status
        );

        $this->assertSame(
            1,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count()
        );

        $this->assertSame(
            $beforeActions,
            ExecutiveAction::count()
        );

        $this->assertSame(
            $beforeAgreements,
            CommercialAgreement::count()
        );

        $this->assertSame(
            $beforeCoverage,
            CommercialAgreementCoverageReview::count()
        );

        $this->assertTruthStillUnverified(
            entryId: $entry->id,

            humanCaseId: $humanCase->id,

            ledgerCaseId: $ledgerCase->id
        );

        $this->assertNull(
            BankTransaction::query()
                ->findOrFail(
                    BankTransaction::query()
                        ->sole()
                        ->id
                )
                ->client_id
        );

        $this->assertSame(
            $client->id,
            $ledgerCase->subject_id
        );
    }

    public function test_existing_routed_signal_reprocessing_uses_reassessment_without_duplicate_history(): void
    {
        [
            $entry,
            $humanCase,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        $originalPaymentSearchId =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole()
                ->id;

        app(
            CeoSignalRoutingService::class
        )->route(
            entry: $entry->fresh(),

            humanSignalCase: $humanCase->fresh()
        );

        $this->assertSame(
            1,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->count()
        );

        $this->assertSame(
            $originalPaymentSearchId,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole()
                ->id
        );

        $this->assertSame(
            0,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count()
        );
    }

    private function captureVfSignal(): array
    {
        $user =
            User::factory()->create([
                'name' => 'Barrie',
            ]);

        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-REASSESS-001',

            'status' => 'overdue',

            'invoice_date' => '2026-07-30',

            'due_date' => '2026-08-06',

            'currency' => 'GBP',

            'net_amount' => 6015,

            'tax_amount' => 1203,

            'gross_amount' => 7218,

            'paid_amount' => 0,

            'outstanding_amount' => 7218,
        ]);

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: 'VF Electrical invoices and payments need checked.'
            );

        $humanCase =
            InvestigationCase::query()
                ->where(
                    'type',
                    'human_signal'
                )
                ->sole();

        $ledgerCase =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        return [
            $entry,
            $humanCase,
            $ledgerCase,
            $client,
        ];
    }

    private function assertTruthStillUnverified(
        string $entryId,
        string $humanCaseId,
        string $ledgerCaseId
    ): void {
        $entry =
            BusinessMemoryEntry::query()
                ->findOrFail(
                    $entryId
                );

        $humanCase =
            InvestigationCase::query()
                ->findOrFail(
                    $humanCaseId
                );

        $ledgerCase =
            InvestigationCase::query()
                ->findOrFail(
                    $ledgerCaseId
                );

        $this->assertFalse(
            $entry->verified
        );

        $this->assertSame(
            'unverified',
            $entry->metadata[
                'truth_status'
            ]
        );

        $this->assertSame(
            0,
            $humanCase->confidence
        );

        $this->assertNull(
            $humanCase->verdict
        );

        $this->assertSame(
            0,
            $ledgerCase->confidence
        );

        $this->assertNull(
            $ledgerCase->verdict
        );
    }
}
