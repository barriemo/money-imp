<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BusinessMemoryEntry;
use App\Models\Client;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalEvidenceBusReassessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_evidence_change_automatically_evolves_matching_ceo_answer(): void
    {
        [
            $user,
            $client,
            $entry,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        $initial =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole();

        $this->assertSame(
            'bank_evidence_missing',
            $initial->payload[
                'state'
            ]
        );

        $account =
            BankAccount::factory()
                ->create([
                    'name' => 'Business Current Account',

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
                '3g-h-vf-payment'
            ),

            'metadata' => [
            'freeagent_full_description' => 'VF ELECTRICAL SERVICES LTD',

            'freeagent_unexplained_amount' => 7218,
            ],
        ]);

        app(
            InvestigationEvidenceBus::class
        )->publish(
            new EvidenceChange(
                domain: 'bank',

                type: 'bank_transactions_changed',

                subjectType: 'client',

                subjectId: $client->id,

                metadata: [
                    'source' => 'test',
                ]
            )
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
                userId: $user->id
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

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            ledgerCase: $ledgerCase
        );
    }

    public function test_unchanged_financial_evidence_trigger_creates_no_reassessment_event(): void
    {
        [
            ,
            $client,
            $entry,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        app(
            InvestigationEvidenceBus::class
        )->publish(
            new EvidenceChange(
                domain: 'bank',

                type: 'bank_transactions_changed',

                subjectType: 'client',

                subjectId: $client->id
            )
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
            1,
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->count()
        );

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            ledgerCase: $ledgerCase
        );
    }

    public function test_client_scoped_evidence_change_does_not_reassess_another_clients_ceo_question(): void
    {
        [
            ,
            ,
            $entry,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        $other =
            Client::factory()
                ->create([
                    'name' => 'Other Client Ltd',
                ]);

        app(
            InvestigationEvidenceBus::class
        )->publish(
            new EvidenceChange(
                domain: 'bank',

                type: 'bank_transactions_changed',

                subjectType: 'client',

                subjectId: $other->id
            )
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

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            ledgerCase: $ledgerCase
        );
    }

    public function test_global_accounting_change_reassesses_existing_ceo_question_when_invoice_evidence_changes(): void
    {
        [
            $user,
            $client,
            $entry,
            $ledgerCase,
        ] =
            $this->captureVfSignal();

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-AUTO-002',

            'status' => 'overdue',

            'invoice_date' => '2026-07-31',

            'due_date' => '2026-08-07',

            'currency' => 'GBP',

            'net_amount' => 100,

            'tax_amount' => 0,

            'gross_amount' => 100,

            'paid_amount' => 0,

            'outstanding_amount' => 100,
        ]);

        app(
            InvestigationEvidenceBus::class
        )->publish(
            new EvidenceChange(
                domain: 'accounting',

                type: 'invoices_changed',

                metadata: [
                    'source' => 'test',
                ]
            )
        );

        $reassessment =
            $ledgerCase
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        /*
         * The search state is still "bank evidence missing",
         * but the underlying accounting evidence materially
         * changed, so a new append-only snapshot is correct.
         */
        $this->assertSame(
            'bank_evidence_missing',
            $reassessment->payload[
                'previous_state'
            ]
        );

        $this->assertSame(
            'bank_evidence_missing',
            $reassessment->payload[
                'state'
            ]
        );

        $this->assertSame(
            2,
            $reassessment->payload[
                'invoice_count'
            ]
        );

        $this->assertSame(
            7318.0,
            (float) $reassessment->payload[
                'accounting_outstanding'
            ]
        );

        $answer =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            )
                ->sole();

        $this->assertSame(
            'evidence_missing',
            $answer->status
        );

        $this->assertStringContainsString(
            '£7,318.00',
            $answer->summary
        );

        $this->assertStringContainsString(
            '2 invoices',
            $answer->summary
        );

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            ledgerCase: $ledgerCase
        );
    }

    private function captureVfSignal(): array
    {
        $user =
            User::factory()
                ->create([
                    'name' => 'Barrie',
                ]);

        $client =
            Client::factory()
                ->create([
                    'name' => 'VF Electrical Services Ltd',
                ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-AUTO-001',

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

        $ledgerCase =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        return [
            $user,
            $client,
            $entry,
            $ledgerCase,
        ];
    }

    private function assertTruthBoundaryPreserved(
        BusinessMemoryEntry $entry,
        InvestigationCase $ledgerCase
    ): void {
        $entry->refresh();
        $ledgerCase->refresh();

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
            'open',
            $ledgerCase->status
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
