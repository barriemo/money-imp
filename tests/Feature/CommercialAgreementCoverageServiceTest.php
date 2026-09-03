<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageService;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommercialAgreementCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_is_all_effective_active_canonical_services_not_only_services_with_billing(): void
    {
        $client =
            Client::factory()->create();

        $this->service(
            client: $client,
            name: 'Current Active'
        );

        $this->service(
            client: $client,
            name: 'Historical',
            status: 'historical'
        );

        $this->service(
            client: $client,
            name: 'Future Active',
            startsOn: '2026-10-01'
        );

        $this->service(
            client: $client,
            name: 'Ended Active',
            endsOn: '2026-08-31'
        );

        $summary =
            app(
                CommercialAgreementCoverageService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            1,
            $summary['scope_count']
        );

        $this->assertSame(
            0,
            $summary['reviewed_count']
        );

        $this->assertSame(
            1,
            $summary['unresolved_count']
        );

        $this->assertFalse(
            $summary['complete']
        );

        $this->assertSame(
            'not_started',
            $summary['status']
        );
    }

    public function test_terminal_reviews_complete_the_active_service_denominator(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $contracted =
            $this->service(
                client: $client,
                name: 'Monthly Retainer'
            );

        $adHoc =
            $this->service(
                client: $client,
                name: 'Ad Hoc Support'
            );

        $agreement =
            app(
                CommercialAgreementAssertionService::class
            )->confirm(
                clientServiceId: $contracted->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Confirmed monthly retainer.'
            );

        $writer =
            app(
                CommercialAgreementCoverageReviewService::class
            );

        $writer->confirmTerms(
            clientServiceId: $contracted->id,

            commercialAgreementId: $agreement->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Current contractual terms explicitly reviewed.'
        );

        $writer->confirmNoCurrentContract(
            clientServiceId: $adHoc->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Service is available ad hoc with no current contractual commitment.'
        );

        $summary =
            app(
                CommercialAgreementCoverageService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            2,
            $summary['scope_count']
        );

        $this->assertSame(
            2,
            $summary['reviewed_count']
        );

        $this->assertSame(
            2,
            $summary['terminal_count']
        );

        $this->assertSame(
            1,
            $summary['confirmed_terms_count']
        );

        $this->assertSame(
            1,
            $summary['no_current_contract_count']
        );

        $this->assertSame(
            0,
            $summary['unresolved_count']
        );

        $this->assertTrue(
            $summary['complete']
        );

        $this->assertSame(
            'complete',
            $summary['status']
        );
    }

    public function test_needs_more_evidence_is_reviewed_but_non_terminal(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            $this->service(
                client: $client,
                name: 'Annual Domain Renewal'
            );

        app(
            CommercialAgreementCoverageReviewService::class
        )->defer(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Annual contractual amount still requires evidence.'
        );

        $summary =
            app(
                CommercialAgreementCoverageService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertSame(
            1,
            $summary['reviewed_count']
        );

        $this->assertSame(
            0,
            $summary['terminal_count']
        );

        $this->assertSame(
            1,
            $summary['needs_more_evidence_count']
        );

        $this->assertSame(
            1,
            $summary['unresolved_count']
        );

        $this->assertFalse(
            $summary['complete']
        );

        $this->assertSame(
            'incomplete',
            $summary['status']
        );
    }

    public function test_no_current_contract_cannot_contradict_current_confirmed_terms(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            $this->service(
                client: $client,
                name: 'Monthly Retainer'
            );

        app(
            CommercialAgreementAssertionService::class
        )->confirm(
            clientServiceId: $service->id,

            cadence: 'monthly',

            contractedAmountPence: 50000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-01-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'signed_agreement',

            reason: 'Confirmed current terms.'
        );

        $this->expectException(
            ValidationException::class
        );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmNoCurrentContract(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Contradictory review must fail.'
        );
    }

    public function test_future_coverage_review_does_not_hide_current_review_before_effective_date(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            $this->service(
                client: $client,
                name: 'Flexible Support'
            );

        $writer =
            app(
                CommercialAgreementCoverageReviewService::class
            );

        $writer->confirmNoCurrentContract(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'No current commitment.'
        );

        $writer->defer(
            clientServiceId: $service->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-10-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'October commercial position requires re-review.'
        );

        $september =
            app(
                CommercialAgreementCoverageService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $this->assertTrue(
            $september['complete']
        );

        $this->assertSame(
            1,
            $september[
                'no_current_contract_count'
            ]
        );

        $october =
            app(
                CommercialAgreementCoverageService::class
            )->summary(
                CarbonImmutable::parse(
                    '2026-10-02'
                )
            );

        $this->assertFalse(
            $october['complete']
        );

        $this->assertSame(
            1,
            $october[
                'needs_more_evidence_count'
            ]
        );
    }

    public function test_confirmed_terms_must_reference_the_current_agreement_for_the_review_date(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            $this->service(
                client: $client,
                name: 'Monthly Support'
            );

        $agreements =
            app(
                CommercialAgreementAssertionService::class
            );

        $current =
            $agreements->confirm(
                clientServiceId: $service->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Current September terms.'
            );

        $future =
            $agreements->supersede(
                commercialAgreementId: $current->id,

                cadence: 'monthly',

                contractedAmountPence: 75000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-10-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'email',

                reason: 'Future October terms.'
            );

        $this->expectException(
            ValidationException::class
        );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmTerms(
            clientServiceId: $service->id,

            commercialAgreementId: $future->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-03'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Future agreement must not count as current September coverage.'
        );
    }

    public function test_coverage_review_is_immutable_below_eloquent(): void
    {
        $client =
            Client::factory()->create();

        $reviewer =
            User::factory()->create();

        $service =
            $this->service(
                client: $client,
                name: 'Ad Hoc Support'
            );

        $review =
            app(
                CommercialAgreementCoverageReviewService::class
            )->confirmNoCurrentContract(
                clientServiceId: $service->id,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-09-03'
                ),

                reviewedBy: $reviewer->id,

                source: 'owner_review',

                reason: 'Explicit no-current-contract review.'
            );

        foreach (
            [
                'update' => fn () => DB::table(
                    'commercial_agreement_coverage_reviews'
                )
                    ->where(
                        'id',
                        $review->id
                    )
                    ->update([
                        'reason' => 'MUTATION MUST FAIL',
                    ]),

                'delete' => fn () => DB::table(
                    'commercial_agreement_coverage_reviews'
                )
                    ->where(
                        'id',
                        $review->id
                    )
                    ->delete(),
            ] as $label => $operation
        ) {
            try {
                $operation();

                $this->fail(
                    $label
                    .' unexpectedly succeeded'
                );
            } catch (
                QueryException $exception
            ) {
                $this->assertStringContainsString(
                    'immutable',
                    strtolower(
                        $exception->getMessage()
                    )
                );
            }
        }

        $this->assertSame(
            1,
            CommercialAgreementCoverageReview::count()
        );
    }

    private function service(
        Client $client,
        string $name,
        string $status = 'active',
        ?string $startsOn = null,
        ?string $endsOn = null
    ): ClientService {
        return ClientService::create([
            'client_id' => $client->id,

            'name' => $name,

            'type' => 'service',

            'status' => $status,

            'starts_on' => $startsOn,

            'ends_on' => $endsOn,
        ]);
    }
}
