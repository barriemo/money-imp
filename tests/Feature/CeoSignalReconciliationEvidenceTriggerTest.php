<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Domains\Reconciliation\Services\PaymentAllocationApprovalService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BusinessMemoryEntry;
use App\Models\Client;
use App\Models\InvestigationCase;
use App\Models\PaymentAllocation;
use App\Models\PaymentIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CeoSignalReconciliationEvidenceTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignoring_supported_candidate_automatically_removes_it_from_current_ceo_answer(): void
    {
        [
            $user,
            ,
            $entry,
            $case,
            ,
            $candidate,
        ] =
            $this->supportedCandidateScenario();

        $initial =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            )
                ->sole();

        $this->assertSame(
            'candidate_requires_verification',
            $initial->status
        );

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.ignore',
                    $candidate
                )
            )
            ->assertRedirect();

        $this->assertSame(
            'ignored',
            $candidate
                ->refresh()
                ->match_status
        );

        /*
         * The stale FreeAgent unexplained amount remains on the
         * source record, but the deliberate Money Imp decision
         * must win.
         */
        $this->assertSame(
            60.0,
            (float) $candidate
                ->metadata[
                    'freeagent_unexplained_amount'
                ]
        );

        $reassessment =
            $case
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        $this->assertSame(
            'supported_payment_candidate_found',
            $reassessment->payload[
                'previous_state'
            ]
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $reassessment->payload[
                'state'
            ]
        );

        $this->assertSame(
            0,
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
            'investigation_requires_attention',
            $answer->status
        );

        $this->assertSame(
            'VF Electrical Services Ltd: £60.00 remains unresolved',
            $answer->headline
        );

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            case: $case
        );
    }

    public function test_assigning_supported_payment_to_client_automatically_reassesses_ceo_answer(): void
    {
        [
            $user,
            $client,
            $entry,
            $case,
            ,
            $candidate,
        ] =
            $this->supportedCandidateScenario();

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.assign-client',
                    $candidate
                ),
                [
                    'client_id' => $client->id,

                    'remember_identity' => 1,
                ]
            )
            ->assertRedirect();

        $candidate->refresh();

        $this->assertSame(
            $client->id,
            $candidate->client_id
        );

        $this->assertSame(
            'suggested',
            $candidate->match_status
        );

        $this->assertSame(
            1,
            PaymentIdentity::count()
        );

        $reassessment =
            $case
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        $this->assertSame(
            'supported_payment_candidate_found',
            $reassessment->payload[
                'previous_state'
            ]
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $reassessment->payload[
                'state'
            ]
        );

        $this->assertSame(
            60.0,
            (float) $reassessment->payload[
                'canonical_cash'
            ]
        );

        $this->assertSame(
            0,
            $reassessment->payload[
                'supported_candidate_count'
            ]
        );

        $this->assertTruthBoundaryPreserved(
            entry: $entry,

            case: $case
        );
    }

    public function test_payment_allocation_approval_publishes_client_scoped_evidence_change(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Allocation Client',
            ]);

        $account =
            BankAccount::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'ALLOC-001',

                'status' => 'overdue',

                'invoice_date' => '2026-08-01',

                'due_date' => '2026-08-08',

                'currency' => 'GBP',

                'gross_amount' => 600,

                'outstanding_amount' => 600,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-08-28',

                'amount' => 600,

                'currency' => 'GBP',

                'description' => 'ALLOCATION CLIENT',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    '3g-i-allocation-payment'
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 600,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        $bus =
            Mockery::mock(
                InvestigationEvidenceBus::class
            );

        $bus
            ->shouldReceive(
                'publish'
            )
            ->once()
            ->with(
                Mockery::on(
                    fn (
                        EvidenceChange $change
                    ): bool => $change->domain === 'bank'
                        && $change->type
                            === 'payment_allocation_approved'
                        && $change->subjectType
                            === 'client'
                        && $change->subjectId
                            === $client->id
                        && (
                            $change->metadata[
                                'allocation_id'
                            ] ?? null
                        ) === $allocation->id
                        && (
                            $change->metadata[
                                'bank_transaction_id'
                            ] ?? null
                        ) === $transaction->id
                )
            )
            ->andReturn(
                collect()
            );

        $this->app->instance(
            InvestigationEvidenceBus::class,
            $bus
        );

        $approved =
            app(
                PaymentAllocationApprovalService::class
            )->approve(
                $allocation,
                $user->id
            );

        $this->assertSame(
            'approved',
            $approved->status
        );

        $this->assertSame(
            'reconciled',
            $transaction
                ->refresh()
                ->match_status
        );
    }

    private function supportedCandidateScenario(): array
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

            'invoice_number' => 'VF-HUMAN-001',

            'status' => 'overdue',

            'invoice_date' => '2026-01-01',

            'due_date' => '2026-01-08',

            'currency' => 'GBP',

            'net_amount' => 60,

            'tax_amount' => 0,

            'gross_amount' => 60,

            'paid_amount' => 0,

            'outstanding_amount' => 60,
        ]);

        $account =
            BankAccount::factory()->create([
                'name' => 'Business Current Account',

                'currency' => 'GBP',
            ]);

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $candidate =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'VF ELECTRICAL SERVICES LTD',

                unexplained: 60
            );

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: 'VF Electrical invoices and payments need checked.'
            );

        $case =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        $search =
            $case
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole();

        $this->assertSame(
            'supported_payment_candidate_found',
            $search->payload[
                'state'
            ]
        );

        return [
            $user,
            $client,
            $entry,
            $case,
            $account,
            $candidate,
        ];
    }

    private function bank(
        BankAccount $account,
        string $date,
        float $amount,
        string $description,
        float $unexplained
    ): BankTransaction {
        return BankTransaction::create([
            'bank_account_id' => $account->id,

            'transaction_date' => $date,

            'amount' => $amount,

            'currency' => 'GBP',

            'description' => $description,

            'transaction_type' => 'imported',

            'match_status' => 'unmatched',

            'source_type' => 'freeagent',

            'transaction_hash' => hash(
                'sha256',
                implode(
                    '|',
                    [
                        $account->id,
                        $date,
                        $amount,
                        $description,
                    ]
                )
            ),

            'metadata' => [
            'freeagent_full_description' => $description,

            'freeagent_unexplained_amount' => $unexplained,
            ],
        ]);
    }

    private function assertTruthBoundaryPreserved(
        BusinessMemoryEntry $entry,
        InvestigationCase $case
    ): void {
        $entry->refresh();
        $case->refresh();

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
            $case->status
        );

        $this->assertSame(
            0,
            $case->confidence
        );

        $this->assertNull(
            $case->verdict
        );
    }
}
