<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementEvidence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class CommercialAgreementAssertionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_creates_immutable_human_assertion_bound_to_canonical_service(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 7500,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Explicitly confirmed terms.'
            );

        $this->assertSame(
            $service->id,
            $agreement->client_service_id
        );

        $this->assertSame(
            $service->client_id,
            $agreement->client_id
        );

        $this->assertSame(
            7500,
            $agreement->contracted_amount_pence
        );

        $this->assertSame(
            '75.00',
            $agreement->monthly_equivalent
        );

        $this->assertSame(
            $reviewer->name,
            $agreement->reviewed_by_name
        );

        $this->expectException(
            LogicException::class
        );

        $agreement->update([
            'reason' => 'Silent mutation must fail.',
        ]);
    }

    public function test_explicit_zero_is_valid_truth_but_business_total_remains_unknown(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 0,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Explicitly confirmed zero-price service.'
            );

        $this->assertSame(
            0,
            $agreement->contracted_amount_pence
        );

        $this->assertSame(
            '0.00',
            $agreement->monthly_equivalent
        );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            0.0,
            $truth[
                'confirmed_recurring_monthly_equivalent'
            ]
        );

        /*
         * Explicit known £0 for one service is still not proof that
         * total contracted value across the business is £0.
         */
        $this->assertNull(
            $truth[
                'contracted_monthly_value'
            ]
        );
    }

    public function test_annual_and_one_off_monthly_equivalents_are_exactly_separated(): void
    {
        [$annualService, $reviewer] =
            $this->serviceAndReviewer();

        $annual =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $annualService->id,

                cadence: 'annual',

                contractedAmountPence: 12000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'proposal',

                reason: 'Annual amount explicitly agreed.'
            );

        $this->assertSame(
            '10.00',
            $annual->monthly_equivalent
        );

        $client =
            Client::factory()->create();

        $oneOffService =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'One-off Development',

                'type' => 'service',

                'status' => 'active',
            ]);

        $oneOff =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $oneOffService->id,

                cadence: 'one_off',

                contractedAmountPence: 250000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-09-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'proposal',

                reason: 'One-off amount explicitly agreed.'
            );

        $this->assertNull(
            $oneOff->monthly_equivalent
        );
    }

    public function test_second_root_assertion_for_same_service_is_rejected(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $writer =
            app(
                CommercialAgreementAssertionService::class
            );

        $writer->confirm(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 7500,

            effectiveFrom: CarbonImmutable::parse(
                '2026-01-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner',

            reason: 'Initial terms.'
        );

        $this->expectException(
            ValidationException::class
        );

        $writer->confirm(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 10000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-06-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner',

            reason: 'Must use supersession.'
        );
    }

    public function test_supersession_is_append_only_and_old_head_cannot_branch(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $writer =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $writer->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Original terms.'
            );

        $second =
            $writer->supersede(
                commercialAgreementId: $first->id,

                cadence: 'monthly',

                contractedAmountPence: 75000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-06-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'email',

                reason: 'Revised terms.'
            );

        $this->assertSame(
            2,
            CommercialAgreement::count()
        );

        $this->assertSame(
            $first->id,
            $second
                ->supersedes_commercial_agreement_id
        );

        $this->assertSame(
            50000,
            $first
                ->fresh()
                ->contracted_amount_pence
        );

        $this->expectException(
            ValidationException::class
        );

        $writer->supersede(
            commercialAgreementId: $first->id,

            cadence: 'monthly',

            contractedAmountPence: 80000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-07-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner',

            reason: 'History branching must fail.'
        );
    }

    public function test_termination_is_terminal_until_explicit_reactivation_support_exists(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $writer =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $writer->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Original terms.'
            );

        $termination =
            $writer->terminate(
                commercialAgreementId: $first->id,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-08-31'
                ),

                reviewedBy: $reviewer->id,

                source: 'client_confirmation',

                reason: 'Client confirmed termination.'
            );

        $truth =
            app(
                CommercialAgreementTruthService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            0,
            $truth['confirmed_count']
        );

        $this->assertSame(
            1,
            $truth['terminated_count']
        );

        $this->assertNull(
            $truth[
                'contracted_monthly_value'
            ]
        );

        $this->expectException(
            ValidationException::class
        );

        $writer->supersede(
            commercialAgreementId: $termination->id,

            cadence: 'monthly',

            contractedAmountPence: 50000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner',

            reason: 'Reactivation requires an explicit future workflow.'
        );
    }

    public function test_agreement_evidence_is_append_only(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 7500,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Confirmed terms.'
            );

        $evidence =
            CommercialAgreementEvidence::create([
                'commercial_agreement_id' => $agreement->id,

                'type' => 'email',

                'source' => 'owner',

                'reference' => 'message-1',

                'summary' => 'Email confirming monthly amount.',

                'observed_on' => '2026-01-01',

                'observed_value_pence' => 7500,

                'currency' => 'GBP',

                'confidence' => 100,

                'verified' => true,

                'recorded_by' => $reviewer->id,

                'recorded_by_name' => $reviewer->name,

                'recorded_at' => now(),
            ]);

        $this->expectException(
            LogicException::class
        );

        $evidence->update([
            'summary' => 'Mutation must fail.',
        ]);
    }

    public function test_database_rejects_raw_agreement_mutation(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 7500,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Immutable contractual truth.'
            );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'commercial_agreements'
        )
            ->where(
                'id',
                $agreement->id
            )
            ->update([
                'reason' => 'Raw mutation must fail.',
            ]);
    }

    public function test_database_rejects_raw_agreement_deletion(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 7500,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Immutable contractual truth.'
            );

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'commercial_agreements'
        )
            ->where(
                'id',
                $agreement->id
            )
            ->delete();
    }

    public function test_database_rejects_raw_evidence_mutation(): void
    {
        [$service, $reviewer] =
            $this->serviceAndReviewer();

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 7500,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner',

                reason: 'Immutable contractual truth.'
            );

        $evidence =
            CommercialAgreementEvidence::create([
                'commercial_agreement_id' => $agreement->id,

                'type' => 'email',

                'source' => 'owner',

                'summary' => 'Supporting evidence.',

                'confidence' => 100,

                'verified' => true,

                'recorded_by' => $reviewer->id,

                'recorded_by_name' => $reviewer->name,

                'recorded_at' => now(),
            ]);

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'commercial_agreement_evidence'
        )
            ->where(
                'id',
                $evidence->id
            )
            ->update([
                'summary' => 'Raw evidence mutation must fail.',
            ]);
    }

    private function serviceAndReviewer(): array
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,

                'name' => 'Managed Service',

                'type' => 'service',

                'status' => 'active',
            ]);

        $reviewer =
            User::factory()->create([
                'name' => 'Commercial Reviewer',
            ]);

        return [
            $service,
            $reviewer,
        ];
    }
}
