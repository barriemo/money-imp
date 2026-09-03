<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryEntry;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\CommercialAgreementEvidence;
use App\Models\ExecutiveAction;
use App\Models\InvestigationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_signal_enters_memory_and_opens_unverified_investigation_without_creating_truth_or_action(): void
    {
        $user =
            User::factory()->create([
                'name' => 'Barrie',
            ]);

        $content =
            'I am worried MML is swallowing far more time than we priced. Is that actually true?';

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,

                rawInput: $content
            );

        $memory =
            BusinessMemory::query()
                ->whereNull(
                    'subject_type'
                )
                ->whereNull(
                    'subject_id'
                )
                ->where(
                    'title',
                    CeoSignalCaptureService::INBOX_TITLE
                )
                ->firstOrFail();

        $this->assertSame(
            $memory->id,
            $entry->business_memory_id
        );

        $this->assertSame(
            $content,
            $entry->content
        );

        $this->assertSame(
            'ceo_signal_box',
            $entry->source
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
            $user->id,
            $entry->metadata[
                'submitted_by_user_id'
            ]
        );

        $this->assertTrue(
            $entry->metadata[
                'requires_investigation_before_truth'
            ]
        );

        $observations =
            $entry->observations()
                ->get();

        $this->assertCount(
            2,
            $observations
        );

        $this->assertTrue(
            $observations->contains(
                fn ($observation) => $observation->observation_type
                        === BusinessMemoryObservationType::Concern
            )
        );

        $this->assertTrue(
            $observations->contains(
                fn ($observation) => $observation->observation_type
                        === BusinessMemoryObservationType::Question
            )
        );

        $this->assertTrue(
            $observations->every(
                fn ($observation) => $observation->verified
                        === false
            )
        );

        /*
         * The signal must enter the existing investigation
         * machinery, but at zero confidence and without a verdict.
         */
        $case =
            InvestigationCase::query()
                ->sole();

        $this->assertSame(
            'human_signal',
            $case->type
        );

        $this->assertSame(
            'business_memory_entry',
            $case->subject_type
        );

        $this->assertSame(
            $entry->id,
            $case->subject_id
        );

        $this->assertSame(
            $content,
            $case->question
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

        $this->assertSame(
            $entry->id,
            $case->metadata[
                'business_memory_entry_id'
            ]
        );

        $this->assertSame(
            'unverified',
            $case->metadata[
                'truth_status'
            ]
        );

        $this->assertTrue(
            $case->metadata[
                'requires_evidence'
            ]
        );

        /*
         * Opening an investigation is NOT an action or truth write.
         */
        $this->assertSame(
            0,
            ExecutiveAction::count()
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementCoverageReview::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementEvidence::count()
        );
    }

    public function test_authenticated_dashboard_submission_captures_signal_and_opens_investigation(): void
    {
        $user =
            User::factory()->create();

        $response =
            $this->actingAs(
                $user
            )->post(
                route(
                    'ceo-signal.store'
                ),
                [
                    'signal' => 'Why does cash feel worse this month?',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'dashboard'
                )
            )
            ->assertSessionHas(
                'ceo_signal_success'
            );

        $entry =
            BusinessMemoryEntry::query()
                ->firstOrFail();

        $this->assertSame(
            'Why does cash feel worse this month?',
            $entry->content
        );

        $this->assertFalse(
            $entry->verified
        );

        $this->assertSame(
            'ceo_signal_box',
            $entry->source
        );

        $case =
            InvestigationCase::query()
                ->sole();

        $this->assertSame(
            $entry->id,
            $case->subject_id
        );

        $this->assertSame(
            0,
            $case->confidence
        );

        $this->assertNull(
            $case->verdict
        );
    }

    public function test_empty_signal_is_rejected_without_writing_memory_or_investigation(): void
    {
        $user =
            User::factory()->create();

        $response =
            $this->actingAs(
                $user
            )->from(
                route(
                    'dashboard'
                )
            )
                ->post(
                    route(
                        'ceo-signal.store'
                    ),
                    [
                        'signal' => '',
                    ]
                );

        $response
            ->assertRedirect(
                route(
                    'dashboard'
                )
            )
            ->assertSessionHasErrors(
                'signal'
            );

        $this->assertSame(
            0,
            BusinessMemoryEntry::count()
        );

        $this->assertSame(
            0,
            InvestigationCase::count()
        );
    }

    public function test_signal_submission_requires_authentication(): void
    {
        $response =
            $this->post(
                route(
                    'ceo-signal.store'
                ),
                [
                    'signal' => 'Something feels wrong.',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'login'
                )
            );

        $this->assertSame(
            0,
            BusinessMemoryEntry::count()
        );

        $this->assertSame(
            0,
            InvestigationCase::count()
        );
    }
}
