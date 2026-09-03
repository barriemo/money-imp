<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Models\BusinessMemoryEntry;
use App\Models\InvestigationCase;

final class CeoSignalRoutingService
{
    public function __construct(
        private readonly CeoSignalClientResolver $clients,

        private readonly CeoSignalDomainResolver $domains,

        private readonly ClientLedgerAnalysisService $ledger,

        private readonly ClientLedgerRiskService $risks,

        private readonly InvestigationCaseService $cases,
    ) {}

    public function route(
        BusinessMemoryEntry $entry,
        InvestigationCase $humanSignalCase
    ): array {
        /*
         * A successfully routed signal is immutable routing history.
         *
         * Reprocessing may happen during recovery/backfill or an
         * operator retry. Do not duplicate linked-signal or evidence
         * snapshot events for the same Business Memory entry.
         *
         * Unresolved signals are deliberately NOT frozen so they can
         * be retried if better subject/evidence coverage arrives.
         */
        $existingRouting =
            $entry->metadata[
                'routing'
            ] ?? null;

        if (
            is_array(
                $existingRouting
            )
            && (
                $existingRouting[
                    'status'
                ] ?? null
            ) === 'routed'
            && (
                $existingRouting[
                    'linked_investigation_case_id'
                ] ?? null
            ) !== null
        ) {
            return $existingRouting;
        }

        $domain =
            $this->domains
                ->resolve(
                    $entry->content
                );

        if ($domain === null) {
            return $this->storeRouting(
                entry: $entry,

                humanSignalCase: $humanSignalCase,

                routing: [
                    'status' => 'unrouted',

                    'reason' => 'domain_not_resolved',
                ]
            );
        }

        if (
            $domain
            !== 'client_ledger'
        ) {
            return $this->storeRouting(
                entry: $entry,

                humanSignalCase: $humanSignalCase,

                routing: [
                    'status' => 'unrouted',

                    'domain' => $domain,

                    'reason' => 'unsupported_domain',
                ]
            );
        }

        $client =
            $this->clients
                ->resolve(
                    $entry->content
                );

        if (! $client) {
            $routing = [
                'status' => 'unresolved_subject',

                'domain' => $domain,

                'reason' => 'client_not_resolved',
            ];

            $this->cases
                ->event(
                    case: $humanSignalCase,

                    type: 'signal_routing_unresolved',

                    description: 'The signal appears to concern a client ledger, but Money Imp could not resolve one client with enough confidence.',

                    payload: $routing
                );

            return $this->storeRouting(
                entry: $entry,

                humanSignalCase: $humanSignalCase,

                routing: $routing
            );
        }

        $position =
            $this->ledger
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        $risk =
            $this->risks
                ->current()
                ->firstWhere(
                    'clientId',
                    $client->id
                );

        if (
            ! $position
            || ! $risk
        ) {
            $routing = [
                'status' => 'resolved_subject_no_current_ledger_evidence',

                'domain' => $domain,

                'subject_type' => 'client',

                'subject_id' => $client->id,

                'subject_name' => $client->name,
            ];

            $this->cases
                ->event(
                    case: $humanSignalCase,

                    type: 'signal_routing_waiting',

                    description: sprintf(
                        'Money Imp matched the signal to %s but does not currently have a supported client-ledger position to interrogate.',
                        $client->name
                    ),

                    payload: $routing
                );

            return $this->storeRouting(
                entry: $entry,

                humanSignalCase: $humanSignalCase,

                routing: $routing
            );
        }

        /*
         * Reuse the canonical client-ledger investigation
         * container. If one is already active, link to it
         * instead of creating a duplicate.
         */
        $ledgerCase =
            $this->cases
                ->findOrOpenForSubject(
                    type: 'client_ledger',

                    subjectType: 'client',

                    subjectId: $client->id,

                    subjectName: $client->name,

                    title: sprintf(
                        'Investigate ledger anomaly for %s',
                        $client->name
                    ),

                    question: 'Why does the client ledger not reconcile?'
                );

        $routing = [
            'status' => 'routed',

            'domain' => 'client_ledger',

            'subject_type' => 'client',

            'subject_id' => $client->id,

            'subject_name' => $client->name,

            'linked_investigation_case_id' => $ledgerCase->id,

            'invoice_count' => $position->invoiceCount,

            'canonical_cash' => $position->cashReceived,

            'accounting_paid' => $position->accountingReportedPaid,

            'accounting_outstanding' => $position->accountingReportedOutstanding,

            'raw_invoice_evidence' => $position->invoicedDuringPaymentWindow,

            'raw_ledger_difference' => $position->ledgerDifference,

            'risk_classification' => $risk->classification,

            'risk_priority' => $risk->priority,

            'risk_confidence' => $risk->confidence,

            'bank_evidence_may_be_incomplete' => $position->bankEvidenceMayBeIncomplete,

            'truth_status' => 'unverified',
        ];

        /*
         * The human-signal case now knows what domain and
         * financial subject it routed to, but its confidence
         * and verdict remain untouched.
         */
        $this->cases
            ->event(
                case: $humanSignalCase,

                type: 'signal_routed',

                description: sprintf(
                    'CEO signal routed to the client-ledger investigation for %s.',
                    $client->name
                ),

                payload: $routing
            );

        /*
         * The domain investigation receives an auditable link
         * back to the original human input.
         */
        $this->cases
            ->event(
                case: $ledgerCase,

                type: 'human_signal_linked',

                description: sprintf(
                    'CEO signal linked to this investigation for %s.',
                    $client->name
                ),

                payload: [
                    'business_memory_entry_id' => $entry->id,

                    'human_signal_case_id' => $humanSignalCase->id,

                    'source' => 'ceo_signal_box',

                    'truth_status' => 'unverified',
                ]
            );

        /*
         * This is an evidence snapshot, not a verdict.
         *
         * Raw ledger facts remain distinct from the
         * accounting-reported outstanding exposure.
         */
        $this->cases
            ->event(
                case: $ledgerCase,

                type: 'evidence_snapshot',

                description: sprintf(
                    'Current ledger evidence captured for %s after CEO signal routing.',
                    $client->name
                ),

                payload: [
                    'business_memory_entry_id' => $entry->id,

                    'human_signal_case_id' => $humanSignalCase->id,

                    'source' => 'client_ledger_risk',

                    'invoice_count' => $position->invoiceCount,

                    'canonical_cash' => $position->cashReceived,

                    'accounting_paid' => $position->accountingReportedPaid,

                    'accounting_outstanding' => $position->accountingReportedOutstanding,

                    'raw_invoice_evidence' => $position->invoicedDuringPaymentWindow,

                    'raw_ledger_difference' => $position->ledgerDifference,

                    'classification' => $risk->classification,

                    'priority' => $risk->priority,

                    'risk_confidence' => $risk->confidence,

                    'bank_evidence_may_be_incomplete' => $position->bankEvidenceMayBeIncomplete,

                    'reasons' => $risk->reasons,

                    'recommended_evidence_actions' => $risk->actions,

                    'truth_boundary' => 'This is an evidence snapshot. Absence of attributed canonical cash does not prove that no payment exists.',
                ]
            );

        return $this->storeRouting(
            entry: $entry,

            humanSignalCase: $humanSignalCase,

            routing: $routing
        );
    }

    private function storeRouting(
        BusinessMemoryEntry $entry,
        InvestigationCase $humanSignalCase,
        array $routing
    ): array {
        $entryMetadata =
            $entry->metadata
            ?? [];

        $entryMetadata[
            'routing'
        ] =
            $routing;

        $entry->forceFill([
            'metadata' => $entryMetadata,
        ])->save();

        $caseMetadata =
            $humanSignalCase->metadata
            ?? [];

        $caseMetadata[
            'routing'
        ] =
            $routing;

        $humanSignalCase
            ->forceFill([
                'metadata' => $caseMetadata,
            ])
            ->save();

        return $routing;
    }
}
