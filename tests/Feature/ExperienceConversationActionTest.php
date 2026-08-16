<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Experience\Conversation\ExperienceConversationAction;
use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceConversationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_investigation_can_recall_similar_business_experience(): void
    {
        $current =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'current-client',
                'subject_name' => 'Current Client',
                'title' => 'Why does the current client ledger not reconcile?',
                'question' => 'Why does the client ledger not reconcile?',
                'status' => 'waiting',
                'confidence' => 60,
                'current_hypothesis' => 'Historical invoices may have been paid into a bank account not currently represented in Money Imp.',
                'opened_at' => now(),
            ]);

        $historicalCase =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'historical-client',
                'subject_name' => 'Historical Client',
                'title' => 'Historical ledger investigation',
                'status' => 'closed',
                'confidence' => 95,
                'opened_at' => now()
                    ->subMonth(),
                'closed_at' => now()
                    ->subMonth()
                    ->addHour(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => $historicalCase->id,

            'fingerprint' => hash(
                'sha256',
                'experience-conversation-similar'
            ),

            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'historical-client',
            'subject_name' => 'Historical Client',
            'title' => 'Historical ledger investigation',
            'summary' => 'Historical bank evidence was missing from the available ledger evidence.',
            'outcome' => 'Importing the missing historical bank evidence resolved the apparent ledger difference.',
            'confidence' => 95,
            'importance' => 80,
            'hypothesis' => 'The invoices were paid into a historical bank account that was missing from the available evidence.',
            'lessons' => [
            'Check historical bank evidence when accounting records show paid invoices but available bank coverage is incomplete.',
            ],
            'evidence_summary' => [],
            'experienced_at' => now()
                ->subMonth(),
        ]);

        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'current-client',
                subjectName: 'Current Client',
                issue: 'client_ledger_investigation',
                investigationCaseId: $current->id
            );

        $answer =
            app(
                ExperienceConversationAction::class
            )->execute(
                'have we seen this before?',
                $context
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'Historical Client',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Why it matches:',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Previous outcome:',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Importing the missing historical bank evidence',
            $answer->answer
        );
    }

    public function test_previous_resolution_is_presented_as_experience_not_current_truth(): void
    {
        $current =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'current-client',
                'subject_name' => 'Current Client',
                'title' => 'Current investigation',
                'status' => 'waiting',
                'current_hypothesis' => 'Historical bank evidence may be incomplete.',
                'opened_at' => now(),
            ]);

        $historicalCase =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'old-client',
                'subject_name' => 'Old Client',
                'title' => 'Old investigation',
                'status' => 'closed',
                'opened_at' => now()
                    ->subMonth(),
                'closed_at' => now()
                    ->subMonth()
                    ->addHour(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => $historicalCase->id,
            'fingerprint' => hash(
                'sha256',
                'experience-conversation-resolution'
            ),
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'old-client',
            'subject_name' => 'Old Client',
            'title' => 'Old investigation',
            'outcome' => 'Historical bank evidence resolved the mismatch.',
            'confidence' => 90,
            'importance' => 80,
            'hypothesis' => 'Historical bank evidence may be incomplete.',
            'lessons' => [
            'Verify historical bank coverage first.',
            ],
            'evidence_summary' => [],
            'experienced_at' => now()
                ->subMonth(),
        ]);

        $answer =
            app(
                ExperienceConversationAction::class
            )->execute(
                'what solved this before?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'current-client',
                    issue: 'client_ledger_investigation',
                    investigationCaseId: $current->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'What resolved it:',
            $answer->answer
        );

        $this->assertStringContainsString(
            'This is historical experience, not proof',
            $answer->answer
        );
    }
}
