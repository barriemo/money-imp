<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Reassessment\InvestigationReassessmentCoordinator;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationReassessmentCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_relevant_open_cases_are_selected_for_reassessment(): void
    {
        $peak =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $walker =
            Client::factory()->create([
                'name' => 'Walker',
            ]);

        $cases =
            app(
                InvestigationCaseService::class
            );

        $peakCase =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $peak->id,
                subjectName: $peak->name
            );

        $peakCase->forceFill([
            'current_hypothesis' => 'Those invoices were paid into HSBC.',
        ])->save();

        $walkerCase =
            $cases->open(
                type: 'client_ledger',
                title: 'Walker investigation',
                subjectType: 'client',
                subjectId: $walker->id,
                subjectName: $walker->name
            );

        $walkerCase->forceFill([
            'current_hypothesis' => 'Walker payments are missing.',
        ])->save();

        $results =
            app(
                InvestigationReassessmentCoordinator::class
            )->reassessOpenCases(
                type: 'client_ledger',
                subjectType: 'client',
                subjectId: $peak->id
            );

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            $peakCase->id,
            $results->first()->id
        );
    }
}
