<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Models\BusinessExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessExperienceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_investigation_becomes_durable_business_experience_once(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Why does Peak not reconcile?',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $case->forceFill([
            'current_hypothesis' => 'The invoices were paid into the old HSBC account.',
        ])->save();

        $closed =
            $cases->close(
                case: $case,
                verdict: 'The historical HSBC payments explain the apparent ledger difference.',
                confidence: 95,
                reason: 'Matching historical bank evidence resolved the investigation.'
            );

        $this->assertDatabaseCount(
            'business_experiences',
            1
        );

        $experience =
            BusinessExperience::query()
                ->firstOrFail();

        $this->assertSame(
            $closed->id,
            $experience->source_investigation_case_id
        );

        $this->assertSame(
            'client_ledger',
            $experience->type
        );

        $this->assertSame(
            'PEAK RENEWABLES (SCOTLAND) LTD',
            $experience->subject_name
        );

        $this->assertSame(
            95,
            $experience->confidence
        );

        $this->assertSame(
            'The invoices were paid into the old HSBC account.',
            $experience->hypothesis
        );

        $this->assertSame(
            'The historical HSBC payments explain the apparent ledger difference.',
            $experience->outcome
        );

        $cases->close(
            case: $closed,
            verdict: 'The historical HSBC payments explain the apparent ledger difference.',
            confidence: 95
        );

        $this->assertDatabaseCount(
            'business_experiences',
            1
        );

        $this->assertSame(
            $experience->id,
            $closed->refresh()
                ->experience
                ->id
        );
    }
}
