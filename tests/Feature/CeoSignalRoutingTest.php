<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalRoutingService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\CommercialAgreement;
use App\Models\ExecutiveAction;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_vf_signal_routes_to_existing_client_ledger_domain_without_promoting_truth(): void
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

            'invoice_number' => 'VF-001',

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

        $content =
            'right VF Electrical, aboluste nightmare, i dont think theyve paid anything, they owe a forunte, so need to see what invocies we have oustanding and make sure we havent missed any payemnts that need to be aligned to them and see the treu picture';

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: $content
            );

        $routing =
            $entry->metadata[
                'routing'
            ];

        $this->assertSame(
            'routed',
            $routing[
                'status'
            ]
        );

        $this->assertSame(
            'client_ledger',
            $routing[
                'domain'
            ]
        );

        $this->assertSame(
            $client->id,
            $routing[
                'subject_id'
            ]
        );

        $this->assertSame(
            'VF Electrical Services Ltd',
            $routing[
                'subject_name'
            ]
        );

        $this->assertSame(
            7218.0,
            (float) $routing[
                'accounting_outstanding'
            ]
        );

        $this->assertSame(
            0.0,
            (float) $routing[
                'canonical_cash'
            ]
        );

        $this->assertSame(
            'invoice_balance_without_canonical_payment_evidence',
            $routing[
                'risk_classification'
            ]
        );

        $this->assertSame(
            54,
            $routing[
                'risk_priority'
            ]
        );

        $this->assertSame(
            80,
            $routing[
                'risk_confidence'
            ]
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

        $this->assertSame(
            $ledgerCase->id,
            $routing[
                'linked_investigation_case_id'
            ]
        );

        $this->assertSame(
            'business_memory_entry',
            $humanCase->subject_type
        );

        $this->assertSame(
            0,
            $humanCase->confidence
        );

        $this->assertNull(
            $humanCase->verdict
        );

        $this->assertSame(
            'client',
            $ledgerCase->subject_type
        );

        $this->assertSame(
            $client->id,
            $ledgerCase->subject_id
        );

        $this->assertSame(
            0,
            $ledgerCase->confidence
        );

        $this->assertNull(
            $ledgerCase->verdict
        );

        $this->assertTrue(
            $humanCase->events()
                ->where(
                    'type',
                    'signal_routed'
                )
                ->exists()
        );

        $this->assertTrue(
            $ledgerCase->events()
                ->where(
                    'type',
                    'human_signal_linked'
                )
                ->exists()
        );

        $snapshot =
            $ledgerCase->events()
                ->where(
                    'type',
                    'evidence_snapshot'
                )
                ->sole();

        $this->assertSame(
            7218.0,
            (float) $snapshot->payload[
                'accounting_outstanding'
            ]
        );

        $this->assertSame(
            0.0,
            (float) $snapshot->payload[
                'canonical_cash'
            ]
        );

        $this->assertSame(
            -7218.0,
            (float) $snapshot->payload[
                'raw_ledger_difference'
            ]
        );

        $this->assertSame(
            'This is an evidence snapshot. Absence of attributed canonical cash does not prove that no payment exists.',
            $snapshot->payload[
                'truth_boundary'
            ]
        );

        $paymentSearch =
            $ledgerCase->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole();

        $this->assertSame(
            'bank_evidence_missing',
            $paymentSearch->payload[
                'state'
            ]
        );

        $this->assertSame(
            0,
            $paymentSearch->payload[
                'supported_candidate_count'
            ]
        );

        $this->assertStringContainsString(
            'cannot prove that no payment occurred',
            $paymentSearch->payload[
                'truth_boundary'
            ]
        );

        /*
         * Routing and evidence capture are not truth/action writes.
         */
        $this->assertSame(
            0,
            ExecutiveAction::count()
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );
    }

    public function test_dashboard_reports_safe_routing_result_after_vf_signal(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-001',

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

        $response =
            $this->actingAs(
                $user
            )->post(
                route(
                    'ceo-signal.store'
                ),
                [
                    'signal' => 'VF Electrical invoices look unpaid and I want the payments checked.',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'dashboard'
                )
            )
            ->assertSessionHas(
                'ceo_signal_finding',
                function (array $finding): bool {
                    return (
                        $finding[
                            'subject_name'
                        ] ?? null
                    ) === 'VF Electrical Services Ltd'
                        && (
                            $finding[
                                'state'
                            ] ?? null
                        ) === 'evidence_missing'
                        && (
                            $finding[
                                'search_state'
                            ] ?? null
                        ) === 'bank_evidence_missing'
                        && (
                            (float) (
                                $finding[
                                    'accounting_outstanding'
                                ]
                                ?? 0
                            )
                        ) === 7218.0
                        && str_contains(
                            $finding[
                                'summary'
                            ] ?? '',
                            '£7,218.00'
                        )
                        && str_contains(
                            $finding[
                                'summary'
                            ] ?? '',
                            'does not currently have bank transaction evidence'
                        )
                        && str_contains(
                            $finding[
                                'summary'
                            ] ?? '',
                            'cannot be treated as proof that payment was not received'
                        )
                        && str_contains(
                            $finding[
                                'truth_boundary'
                            ] ?? '',
                            'No conclusion about payment presence or absence'
                        );
                }
            );
    }

    public function test_ambiguous_client_name_is_not_guessed(): void
    {
        $user =
            User::factory()->create();

        Client::factory()->create([
            'name' => 'Alpha Services Ltd',
        ]);

        Client::factory()->create([
            'name' => 'Alpha Systems Ltd',
        ]);

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: 'Alpha invoices look wrong and I want the payments checked.'
            );

        $this->assertSame(
            'unresolved_subject',
            $entry->metadata[
                'routing'
            ][
                'status'
            ]
        );

        $this->assertSame(
            1,
            InvestigationCase::query()
                ->where(
                    'type',
                    'human_signal'
                )
                ->count()
        );

        $this->assertSame(
            0,
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->count()
        );
    }

    public function test_routed_signal_can_be_reprocessed_without_duplicate_events(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-IDEMPOTENT',

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

        $originalRouting =
            $entry->metadata[
                'routing'
            ];

        $this->assertSame(
            1,
            $humanCase->events()
                ->where(
                    'type',
                    'signal_routed'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'human_signal_linked'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'evidence_snapshot'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->count()
        );

        $secondRouting =
            app(
                CeoSignalRoutingService::class
            )->route(
                entry: $entry->fresh(),

                humanSignalCase: $humanCase->fresh()
            );

        $this->assertSame(
            $originalRouting,
            $secondRouting
        );

        $this->assertSame(
            1,
            InvestigationCase::query()
                ->where(
                    'type',
                    'human_signal'
                )
                ->count()
        );

        $this->assertSame(
            1,
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $humanCase->events()
                ->where(
                    'type',
                    'signal_routed'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'human_signal_linked'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'evidence_snapshot'
                )
                ->count()
        );

        $this->assertSame(
            1,
            $ledgerCase->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->count()
        );
    }

    public function test_signal_reuses_existing_active_client_ledger_case(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Walker The Jeweller Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'WALKER-001',

            'status' => 'overdue',

            'invoice_date' => '2026-07-30',

            'due_date' => '2026-08-06',

            'currency' => 'GBP',

            'net_amount' => 8058.33,

            'tax_amount' => 1611.67,

            'gross_amount' => 9670,

            'paid_amount' => 0,

            'outstanding_amount' => 9670,
        ]);

        $existing =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',

                title: 'Existing Walker ledger investigation',

                question: 'Why does the client ledger not reconcile?',

                subjectType: 'client',

                subjectId: $client->id,

                subjectName: $client->name
            );

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: 'Walker invoices and payments still need checked.'
            );

        $this->assertSame(
            $existing->id,
            $entry->metadata[
                'routing'
            ][
                'linked_investigation_case_id'
            ]
        );

        $this->assertSame(
            1,
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->where(
                    'subject_id',
                    $client->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            $existing
                ->events()
                ->where(
                    'type',
                    'human_signal_linked'
                )
                ->count()
        );
    }
}
