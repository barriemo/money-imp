<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Experience\Matching\BusinessExperienceMatcher;
use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessExperienceMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_similar_historical_experience_out_ranks_unrelated_experience(): void
    {
        $current =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'walker',
                'subject_name' => 'Walker The Jeweller Ltd',
                'title' => 'Why does Walker not reconcile?',
                'question' => 'Why does the client ledger not reconcile?',
                'status' => 'waiting',
                'confidence' => 60,
                'current_hypothesis' => 'Historical invoices may have been paid into a bank account not currently represented in Money Imp.',
                'opened_at' => now(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'Peak Renewables',
                'title' => 'Peak ledger investigation',
                'status' => 'closed',
                'opened_at' => now()
                    ->subMonth(),
                'closed_at' => now()
                    ->subMonth()
                    ->addHour(),
            ])->id,

            'fingerprint' => hash(
                'sha256',
                'similar-peak'
            ),

            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'peak',
            'subject_name' => 'Peak Renewables',
            'title' => 'Peak ledger investigation',
            'summary' => 'Historical payments were missing from the current bank evidence.',
            'outcome' => 'Historical bank evidence explained the apparent ledger difference.',
            'confidence' => 95,
            'importance' => 80,
            'hypothesis' => 'The invoices were paid into a historical bank account that was missing from the available evidence.',
            'lessons' => [
                'Check historical bank accounts when accounting shows paid invoices but current bank evidence is incomplete.',
            ],
            'evidence_summary' => [],
            'experienced_at' => now()
                ->subMonth(),
        ]);

        BusinessExperience::create([
            'source_investigation_case_id' => InvestigationCase::create([
                'type' => 'supplier_credit',
                'subject_type' => 'supplier',
                'subject_id' => 'supplier-1',
                'subject_name' => 'Example Supplier',
                'title' => 'Supplier credit investigation',
                'status' => 'closed',
                'opened_at' => now()
                    ->subMonths(2),
                'closed_at' => now()
                    ->subMonths(2)
                    ->addHour(),
            ])->id,

            'fingerprint' => hash(
                'sha256',
                'unrelated-supplier'
            ),

            'type' => 'supplier_credit',
            'subject_type' => 'supplier',
            'subject_id' => 'supplier-1',
            'subject_name' => 'Example Supplier',
            'title' => 'Supplier credit investigation',
            'summary' => 'A supplier refund was incorrectly allocated.',
            'outcome' => 'The supplier allocation was corrected.',
            'confidence' => 90,
            'importance' => 65,
            'hypothesis' => 'The supplier refund was posted twice.',
            'lessons' => [
                'Review duplicated supplier refunds.',
            ],
            'evidence_summary' => [],
            'experienced_at' => now()
                ->subMonths(2),
        ]);

        $matches =
            app(
                BusinessExperienceMatcher::class
            )->forInvestigation(
                $current
            );

        $this->assertNotEmpty(
            $matches
        );

        $this->assertSame(
            'Peak Renewables',
            $matches->first()
                ->experience
                ->subject_name
        );

        $this->assertGreaterThan(
            30,
            $matches->first()
                ->score
        );

        $this->assertContains(
            'Same investigation type.',
            $matches->first()
                ->reasons
        );
    }
}
