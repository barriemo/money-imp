<?php

namespace App\Console\Commands;

use App\Domains\CommercialTruth\Services\CommercialAgreementHumanReviewService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommercialAgreementHumanReviewCommand extends Command
{
    protected $signature =
        'money:contract-review
        {clientServiceId : Canonical ClientService UUID}
        {decision : establish_terms|confirm_terms|no_current_contract|needs_more_evidence}
        {--effective-from= : Effective date YYYY-MM-DD}
        {--reviewer-email= : Existing Money Imp reviewer email}
        {--source= : Human evidence source}
        {--source-reference= : Optional source reference}
        {--reason= : Human review reason}
        {--cadence= : monthly|quarterly|annual|one_off for establish_terms}
        {--amount-pence= : Exact contractual amount in pence for establish_terms}
        {--effective-to= : Optional contractual end date YYYY-MM-DD}
        {--renews-on= : Optional renewal date YYYY-MM-DD}
        {--execute : Persist the reviewed contractual truth}';

    protected $description =
        'Preview or explicitly record one human-reviewed contractual coverage decision';

    public function handle(
        CommercialAgreementHumanReviewService $reviews
    ): int {
        try {
            $decision =
                str_replace(
                    '-',
                    '_',
                    trim(
                        (string) $this->argument(
                            'decision'
                        )
                    )
                );

            if (
                ! in_array(
                    $decision,
                    [
                        CommercialAgreementHumanReviewService::ACTION_ESTABLISH_TERMS,
                        CommercialAgreementHumanReviewService::ACTION_CONFIRM_TERMS,
                        CommercialAgreementHumanReviewService::ACTION_NO_CURRENT_CONTRACT,
                        CommercialAgreementHumanReviewService::ACTION_NEEDS_MORE_EVIDENCE,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'decision' => 'Unsupported contract review decision.',
                ]);
            }

            $effectiveFrom =
                $this->dateOption(
                    'effective-from',
                    required: (bool) $this->option(
                        'execute'
                    ),
                    fallback: CarbonImmutable::today()
                );

            $candidate =
                $reviews->preview(
                    clientServiceId: (string) $this->argument(
                        'clientServiceId'
                    ),

                    asOf: $effectiveFrom
                );

            $this->presentPreview(
                decision: $decision,

                effectiveFrom: $effectiveFrom,

                candidate: $candidate
            );

            if (
                ! $this->option(
                    'execute'
                )
            ) {
                $this->warn(
                    'DRY RUN ONLY — no contractual or coverage truth was written.'
                );

                $this->line(
                    'Re-run with --execute plus explicit reviewer/source/reason inputs to persist this human decision.'
                );

                return self::SUCCESS;
            }

            $reviewerEmail =
                $this->requiredOption(
                    'reviewer-email'
                );

            $source =
                $this->requiredOption(
                    'source'
                );

            $reason =
                $this->requiredOption(
                    'reason'
                );

            $sourceReference =
                $this->optionalOption(
                    'source-reference'
                );

            $reviewer =
                User::query()
                    ->where(
                        'email',
                        $reviewerEmail
                    )
                    ->firstOrFail();

            match (
                $decision
            ) {
                CommercialAgreementHumanReviewService::ACTION_ESTABLISH_TERMS => $this->executeEstablishTerms(
                    reviews: $reviews,

                    clientServiceId: $candidate
                        ->clientServiceId,

                    effectiveFrom: $effectiveFrom,

                    reviewedBy: $reviewer->id,

                    source: $source,

                    reason: $reason,

                    sourceReference: $sourceReference
                ),

                CommercialAgreementHumanReviewService::ACTION_CONFIRM_TERMS => $reviews->confirmCurrentTerms(
                    clientServiceId: $candidate
                        ->clientServiceId,

                    effectiveFrom: $effectiveFrom,

                    reviewedBy: $reviewer->id,

                    source: $source,

                    reason: $reason,

                    sourceReference: $sourceReference
                ),

                CommercialAgreementHumanReviewService::ACTION_NO_CURRENT_CONTRACT => $reviews->confirmNoCurrentContract(
                    clientServiceId: $candidate
                        ->clientServiceId,

                    effectiveFrom: $effectiveFrom,

                    reviewedBy: $reviewer->id,

                    source: $source,

                    reason: $reason,

                    sourceReference: $sourceReference
                ),

                CommercialAgreementHumanReviewService::ACTION_NEEDS_MORE_EVIDENCE => $reviews->defer(
                    clientServiceId: $candidate
                        ->clientServiceId,

                    effectiveFrom: $effectiveFrom,

                    reviewedBy: $reviewer->id,

                    source: $source,

                    reason: $reason,

                    sourceReference: $sourceReference
                ),
            };

            $this->newLine();

            $this->info(
                'Human contract review persisted.'
            );

            $this->line(
                'Decision: '
                .$decision
            );

            $this->line(
                'Client: '
                .$candidate->clientName
            );

            $this->line(
                'Service: '
                .$candidate->serviceName
            );

            $this->line(
                'Reviewer: '
                .$reviewer->name
                .' <'
                .$reviewer->email
                .'>'
            );

            return self::SUCCESS;
        } catch (
            ValidationException $exception
        ) {
            foreach (
                $exception->errors() as $messages
            ) {
                foreach (
                    $messages as $message
                ) {
                    $this->error(
                        $message
                    );
                }
            }

            return self::FAILURE;
        } catch (
            Throwable $exception
        ) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function executeEstablishTerms(
        CommercialAgreementHumanReviewService $reviews,
        string $clientServiceId,
        CarbonImmutable $effectiveFrom,
        int $reviewedBy,
        string $source,
        string $reason,
        ?string $sourceReference
    ): void {
        $cadence =
            $this->requiredOption(
                'cadence'
            );

        $amountPenceRaw =
            $this->option(
                'amount-pence'
            );

        if (
            $amountPenceRaw === null
            || $amountPenceRaw === ''
            || ! preg_match(
                '/^\d+$/',
                (string) $amountPenceRaw
            )
        ) {
            throw ValidationException::withMessages([
                'amount-pence' => 'establish_terms requires an exact non-negative integer --amount-pence value.',
            ]);
        }

        $reviews->establishTerms(
            clientServiceId: $clientServiceId,

            cadence: $cadence,

            contractedAmountPence: (int) $amountPenceRaw,

            effectiveFrom: $effectiveFrom,

            reviewedBy: $reviewedBy,

            source: $source,

            reason: $reason,

            effectiveTo: $this->dateOption(
                'effective-to'
            ),

            renewsOn: $this->dateOption(
                'renews-on'
            ),

            sourceReference: $sourceReference
        );
    }

    private function presentPreview(
        string $decision,
        CarbonImmutable $effectiveFrom,
        object $candidate
    ): void {
        $this->newLine();

        $this->info(
            'Human Contract Review'
        );

        $this->line(
            'Decision: '
            .$decision
        );

        $this->line(
            'Effective from: '
            .$effectiveFrom->toDateString()
        );

        $this->line(
            'Client: '
            .$candidate->clientName
        );

        $this->line(
            'Service: '
            .$candidate->serviceName
        );

        $this->line(
            'ClientService: '
            .$candidate->clientServiceId
        );

        $this->line(
            'Coverage: '
            .$candidate->coverageState
        );

        $this->line(
            'Priority: '
            .$candidate->priority
        );

        $this->line(
            'Observed state: '
            .$candidate->observedBillingState
        );

        $this->line(
            'Observed cadence: '
            .(
                $candidate->observedCadence
                ?? 'UNKNOWN'
            )
        );

        $this->line(
            'Observed freshness: '
            .(
                $candidate->observedFreshness
                ?? 'UNKNOWN'
            )
        );

        $this->line(
            'Observed current £/mo: '
            .(
                $candidate
                    ->observedCurrentMonthlyEquivalent
                !== null
                    ? '£'.number_format(
                        $candidate
                            ->observedCurrentMonthlyEquivalent,
                        2
                    )
                    : 'UNKNOWN'
            )
        );

        $this->line(
            'Current agreement: '
            .(
                $candidate
                    ->currentAgreementId
                ?? 'NONE'
            )
        );

        $this->line(
            'Available actions: '
            .implode(
                ', ',
                $candidate
                    ->availableDecisions
            )
        );

        $this->newLine();
    }

    private function requiredOption(
        string $name
    ): string {
        $value =
            trim(
                (string) (
                    $this->option(
                        $name
                    )
                    ?? ''
                )
            );

        if (
            $value === ''
        ) {
            throw ValidationException::withMessages([
                $name => '--'
                    .$name
                    .' is required when --execute is used.',
            ]);
        }

        return $value;
    }

    private function optionalOption(
        string $name
    ): ?string {
        $value =
            trim(
                (string) (
                    $this->option(
                        $name
                    )
                    ?? ''
                )
            );

        return $value !== ''
            ? $value
            : null;
    }

    private function dateOption(
        string $name,
        bool $required = false,
        ?CarbonImmutable $fallback = null
    ): ?CarbonImmutable {
        $value =
            $this->option(
                $name
            );

        if (
            $value === null
            || trim(
                (string) $value
            ) === ''
        ) {
            if (
                $required
            ) {
                throw ValidationException::withMessages([
                    $name => '--'
                        .$name
                        .' is required when --execute is used.',
                ]);
            }

            return $fallback;
        }

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                trim(
                    (string) $value
                )
            )->startOfDay();
        } catch (
            Throwable
        ) {
            throw ValidationException::withMessages([
                $name => '--'
                    .$name
                    .' must be YYYY-MM-DD.',
            ]);
        }
    }
}
