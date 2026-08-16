<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Reassessment\EvidenceTrigger;
use App\Domains\BusinessBrain\Investigation\Reassessment\InvestigationReassessmentService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationReassessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassessment_only_records_changes_in_belief(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'reassessment-peak-bank'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those large invoices were paid into our old HSBC account.',
        ])->save();

        $service =
            app(
                InvestigationReassessmentService::class
            );

        $service->reassess(
            $case
        );

        $firstCount =
            $case->events()
                ->count();

        $service->reassess(
            $case
        );

        $this->assertSame(
            $firstCount,
            $case->events()
                ->count()
        );
    }

    public function test_new_bank_evidence_changes_existing_investigation_belief(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $currentAccount =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $currentAccount->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'belief-change-existing-bank'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those large invoices were paid into our old HSBC account.',
        ])->save();

        $service =
            app(
                InvestigationReassessmentService::class
            );

        $service->reassess(
            $case
        );

        $before =
            $case->refresh();

        $initialConfidence =
            $before->confidence;

        $this->assertGreaterThan(
            0,
            $initialConfidence
        );

        $initialDestinationClaim =
            $case->events()
                ->where(
                    'type',
                    'claim_assessed'
                )
                ->get()
                ->first(
                    fn ($event) => ($event->payload['key'] ?? null)
                        === 'payment_destination_hsbc'
                );

        $this->assertNotNull(
            $initialDestinationClaim
        );

        $this->assertSame(
            'unverified',
            $initialDestinationClaim->payload[
                'status'
            ]
        );

        $hsbc =
            BankAccount::create([
                'name' => 'HSBC Old Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'inactive',
            ]);

        BankTransaction::create([
            'bank_account_id' => $hsbc->id,
            'client_id' => $client->id,
            'transaction_date' => '2025-10-25',
            'amount' => 21990,
            'description' => 'PEAK RENEWABLES INV1686',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'belief-change-hsbc-1686'
            ),
        ]);

        $service->reassess(
            $case
        );

        $changedClaim =
            $case->events()
                ->where(
                    'type',
                    'claim_changed'
                )
                ->get()
                ->first(
                    fn ($event) => ($event->payload['key'] ?? null)
                        === 'payment_destination_hsbc'
                );

        $this->assertNotNull(
            $changedClaim
        );

        $this->assertSame(
            'unverified',
            $changedClaim->payload[
                'previous_status'
            ]
        );

        $this->assertSame(
            'supported',
            $changedClaim->payload[
                'status'
            ]
        );

        $this->assertSame(
            95,
            $changedClaim->payload[
                'confidence'
            ]
        );

        $after =
            $case->refresh();

        $this->assertNotSame(
            $initialConfidence,
            $after->confidence
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'hypothesis_changed',
            ]
        );
    }

    public function test_evidence_trigger_is_recorded_when_reassessment_changes_belief(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'trigger-recorded-peak'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into our old HSBC account.',
        ])->save();

        app(
            InvestigationReassessmentService::class
        )->reassess(
            $case,
            new EvidenceTrigger(
                domain: 'bank',
                type: 'bank_transactions_changed',
                metadata: [
                    'sync_run_id' => 'trigger-run-1',
                ]
            )
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'evidence_changed',
            ]
        );

        $event =
            $case->events()
                ->where(
                    'type',
                    'evidence_changed'
                )
                ->firstOrFail();

        $this->assertSame(
            'bank',
            $event->payload[
                'domain'
            ]
        );

        $this->assertSame(
            'bank_transactions_changed',
            $event->payload[
                'type'
            ]
        );

        $this->assertSame(
            'trigger-run-1',
            $event->payload[
                'metadata'
            ][
                'sync_run_id'
            ]
        );
    }

    public function test_identical_reassessment_does_not_duplicate_evidence_trigger(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'trigger-dedup-peak'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into our old HSBC account.',
        ])->save();

        $service =
            app(
                InvestigationReassessmentService::class
            );

        $trigger =
            new EvidenceTrigger(
                domain: 'bank',
                type: 'bank_transactions_changed',
                metadata: [
                    'sync_run_id' => 'trigger-run-2',
                ]
            );

        $service->reassess(
            $case,
            $trigger
        );

        $firstCount =
            $case->events()
                ->where(
                    'type',
                    'evidence_changed'
                )
                ->count();

        $this->assertSame(
            1,
            $firstCount
        );

        $service->reassess(
            $case,
            $trigger
        );

        $this->assertSame(
            $firstCount,
            $case->events()
                ->where(
                    'type',
                    'evidence_changed'
                )
                ->count()
        );
    }

    public function test_reassessment_events_share_trigger_correlation_id(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'correlation-id-peak'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into our old HSBC account.',
        ])->save();

        $trigger =
            new EvidenceTrigger(
                domain: 'bank',
                type: 'bank_transactions_changed',
                correlationId: 'test-correlation-123'
            );

        app(
            InvestigationReassessmentService::class
        )->reassess(
            $case,
            $trigger
        );

        $correlated =
            $case->events()
                ->get()
                ->filter(
                    fn ($event) => ($event->payload['correlation_id'] ?? null)
                        === 'test-correlation-123'
                );

        $this->assertTrue(
            $correlated->contains(
                fn ($event) => $event->type === 'evidence_changed'
            )
        );

        $this->assertTrue(
            $correlated->contains(
                fn ($event) => in_array(
                    $event->type,
                    [
                        'hypothesis_assessed',
                        'hypothesis_changed',
                    ],
                    true
                )
            )
        );

        $this->assertTrue(
            $correlated->contains(
                fn ($event) => in_array(
                    $event->type,
                    [
                        'claim_assessed',
                        'claim_changed',
                    ],
                    true
                )
            )
        );
    }
}
