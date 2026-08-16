<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationEvidenceBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_bank_evidence_reassesses_only_relevant_client_case(): void
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
                InvestigationEvidenceBus::class
            )->publish(
                new EvidenceChange(
                    domain: 'bank',
                    type: 'transactions_imported',
                    subjectType: 'client',
                    subjectId: $peak->id
                )
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

    public function test_unknown_evidence_domain_does_not_reassess_cases(): void
    {
        $results =
            app(
                InvestigationEvidenceBus::class
            )->publish(
                new EvidenceChange(
                    domain: 'weather',
                    type: 'forecast_changed'
                )
            );

        $this->assertCount(
            0,
            $results
        );
    }
}
