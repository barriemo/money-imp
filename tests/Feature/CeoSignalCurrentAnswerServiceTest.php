<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\ExecutiveAction;
use App\Models\InvestigationCase;
use App\Models\InvestigationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalCurrentAnswerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_answer_reconstructs_latest_safe_finding_without_writing_truth(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'VF-CURRENT-001',

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

        $question =
            'VF Electrical invoices look unpaid and I want the payments checked.';

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: $question
            );

        $case =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        $before = [
            'entry_verified' => $entry->fresh()->verified,

            'case_status' => $case->fresh()->status,

            'case_confidence' => $case->fresh()->confidence,

            'case_verdict' => $case->fresh()->verdict,

            'event_count' => InvestigationEvent::count(),

            'executive_actions' => ExecutiveAction::count(),

            'commercial_agreements' => CommercialAgreement::count(),

            'coverage_reviews' => CommercialAgreementCoverageReview::count(),
        ];

        $answers =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            );

        $this->assertCount(
            1,
            $answers
        );

        $answer =
            $answers->sole();

        $this->assertSame(
            $entry->id,
            $answer->entryId
        );

        $this->assertSame(
            $question,
            $answer->question
        );

        $this->assertSame(
            'evidence_missing',
            $answer->status
        );

        $this->assertSame(
            'Waiting for bank evidence',
            $answer->statusLabel
        );

        $this->assertSame(
            'VF Electrical Services Ltd: bank evidence is missing',
            $answer->headline
        );

        $this->assertStringContainsString(
            '£7,218.00',
            $answer->summary
        );

        $this->assertStringContainsString(
            'cannot be treated as proof that payment was not received',
            $answer->summary
        );

        $after = [
            'entry_verified' => $entry->fresh()->verified,

            'case_status' => $case->fresh()->status,

            'case_confidence' => $case->fresh()->confidence,

            'case_verdict' => $case->fresh()->verdict,

            'event_count' => InvestigationEvent::count(),

            'executive_actions' => ExecutiveAction::count(),

            'commercial_agreements' => CommercialAgreement::count(),

            'coverage_reviews' => CommercialAgreementCoverageReview::count(),
        ];

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_current_answers_are_scoped_to_the_submitting_user(): void
    {
        $first =
            User::factory()->create();

        $second =
            User::factory()->create();

        $firstEntry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $first,

                rawInput: 'Is our delivery capacity becoming a problem?'
            );

        app(
            CeoSignalCaptureService::class
        )->capture(
            submittedBy: $second,

            rawInput: 'Do we have a marketing issue?'
        );

        $answers =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $first->id
            );

        $this->assertCount(
            1,
            $answers
        );

        $this->assertSame(
            $firstEntry->id,
            $answers->sole()->entryId
        );

        $this->assertSame(
            'Is our delivery capacity becoming a problem?',
            $answers->sole()->question
        );
    }

    public function test_dashboard_reloads_existing_brain_answer_after_original_flash_is_gone(): void
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

            'invoice_number' => 'VF-DASH-001',

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

        $question =
            'VF Electrical invoices look unpaid and I want the payments checked.';

        app(
            CeoSignalCaptureService::class
        )->capture(
            submittedBy: $user,

            rawInput: $question
        );

        $response =
            $this->actingAs(
                $user
            )->get(
                route(
                    'dashboard'
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'What you asked the Brain'
            )
            ->assertSee(
                $question
            )
            ->assertSee(
                'Waiting for bank evidence'
            )
            ->assertSee(
                'VF Electrical Services Ltd: bank evidence is missing'
            )
            ->assertSee(
                '£7,218.00'
            )
            ->assertSee(
                'Truth boundary:'
            );
    }
}
