<?php

namespace App\Console\Commands;

use App\Domains\Reconciliation\Services\ReconciliationCandidateService;
use App\Domains\Reconciliation\Services\ReconciliationEvidencePublisher;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateReconciliationCandidates extends Command
{
    protected $signature = 'money-imp:reconciliation-candidates';

    protected $description = 'Rebuild reconciliation suggestions from unmatched bank receipts';

    public function handle(
        ReconciliationCandidateService $service,
        ReconciliationEvidencePublisher $evidence
    ): int {
        $this->info('Rebuilding reconciliation candidates...');

        $reset =
            DB::transaction(function (): array {
                /*
                 * This rebuild owns only the provisional
                 * suggestions created by ReconciliationCandidateService.
                 *
                 * Human-attributed transactions and suggestions
                 * belonging to other payment engines must survive.
                 *
                 * Match method is not enough to prove machine ownership.
                 * Legacy rows may use an automated-looking method without
                 * carrying provenance. Only suggestions whose transaction
                 * is explicitly marked automated_candidate are rebuild-owned.
                 */
                $suggestedAllocations =
                    PaymentAllocation::query()
                        ->where(
                            'status',
                            'suggested'
                        )
                        ->whereIn(
                            'match_method',
                            [
                                'client_and_exact_amount',
                                'client_and_invoice_reference',
                            ]
                        )
                        ->whereHas(
                            'transaction',
                            fn ($query) => $query
                                ->where(
                                    'match_status',
                                    'suggested'
                                )
                                ->whereNull(
                                    'matched_by'
                                )
                                ->where(
                                    'metadata->reconciliation_provenance',
                                    'automated_candidate'
                                )
                        )
                        ->count();

                $machineSuggestedTransactions =
                    BankTransaction::query()
                        ->where(
                            'match_status',
                            'suggested'
                        )
                        ->whereNull(
                            'matched_by'
                        )
                        ->where(
                            'metadata->reconciliation_provenance',
                            'automated_candidate'
                        )
                        ->count();

                PaymentAllocation::query()
                    ->where(
                        'status',
                        'suggested'
                    )
                    ->whereIn(
                        'match_method',
                        [
                            'client_and_exact_amount',
                            'client_and_invoice_reference',
                        ]
                    )
                    ->whereHas(
                        'transaction',
                        fn ($query) => $query
                            ->where(
                                'match_status',
                                'suggested'
                            )
                            ->whereNull(
                                'matched_by'
                            )
                            ->where(
                                'metadata->reconciliation_provenance',
                                'automated_candidate'
                            )
                    )
                    ->delete();

                BankTransaction::query()
                    ->where(
                        'match_status',
                        'suggested'
                    )
                    ->whereNull(
                        'matched_by'
                    )
                    ->where(
                        'metadata->reconciliation_provenance',
                        'automated_candidate'
                    )
                    ->update([
                        'client_id' => null,
                        'match_status' => 'unmatched',
                        'match_confidence' => null,
                        'transaction_type' => 'imported',
                    ]);

                return [
                    'suggested_allocations_reset' => $suggestedAllocations,

                    'machine_suggested_transactions_reset' => $machineSuggestedTransactions,
                ];
            });

        /*
         * Do not publish the temporary reset state.
         * Rebuild first, then publish one final evidence change.
         */
        $stats =
            $service->generate(
                publishEvidence: false
            );

        if (
            $reset[
                'suggested_allocations_reset'
            ] > 0
            || $reset[
                'machine_suggested_transactions_reset'
            ] > 0
            || $stats[
                'classified_non_client'
            ] > 0
            || $stats[
                'client_matches'
            ] > 0
            || $stats[
                'invoice_matches'
            ] > 0
        ) {
            $evidence->publish(
                type: 'reconciliation_candidates_rebuilt',

                metadata: array_merge(
                    $reset,
                    $stats
                )
            );
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Considered', $stats['considered']],
                ['Classified non-client', $stats['classified_non_client']],
                ['Client matches', $stats['client_matches']],
                ['Invoice matches', $stats['invoice_matches']],
                ['Ambiguous', $stats['ambiguous']],
                ['Still unmatched', $stats['unmatched']],
            ]
        );

        return self::SUCCESS;
    }
}
