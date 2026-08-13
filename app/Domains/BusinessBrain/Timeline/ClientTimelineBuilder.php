<?php

namespace App\Domains\BusinessBrain\Timeline;

use App\Models\AccountingInvoice;
use App\Models\BusinessDecisionOutcome;
use App\Models\BusinessMemoryEvent;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientTimelineBuilder
{
    public function build(
        Client $client
    ): ClientTimeline {
        $events =
            collect()
                ->merge(
                    $this->invoiceEvents(
                        $client
                    )
                )
                ->merge(
                    $this->paymentEvents(
                        $client
                    )
                )
                ->merge(
                    $this->decisionEvents(
                        $client
                    )
                )
                ->merge(
                    $this->memoryEvents(
                        $client
                    )
                )
                ->unique(
                    fn (TimelineEvent $event) => $event->identity
                )
                ->sortByDesc(
                    fn (TimelineEvent $event) => $event
                        ->occurredAt
                        ->timestamp
                )
                ->values();

        return new ClientTimeline(
            client: $client,

            events: $events
        );
    }

    private function invoiceEvents(
        Client $client
    ): Collection {
        return AccountingInvoice::query()
            ->where(
                'client_id',
                $client->id
            )
            ->get()
            ->map(
                fn (AccountingInvoice $invoice) => new TimelineEvent(
                    occurredAt: Carbon::parse(
                        $invoice->invoice_date
                    ),

                    type: 'invoice',

                    title: sprintf(
                        'Invoice %s raised',
                        $invoice->invoice_number
                    ),

                    description: sprintf(
                        'Invoice for £%s was raised with status %s.',
                        number_format(
                            (float) $invoice->gross_amount,
                            2
                        ),
                        $invoice->status
                    ),

                    value: (float) $invoice->gross_amount,

                    importance: 60,

                    identity: 'invoice:'.$invoice->id,

                    metadata: [
                        'invoice_id' => $invoice->id,

                        'invoice_number' => $invoice->invoice_number,

                        'status' => $invoice->status,

                        'outstanding_amount' => (float) $invoice
                            ->outstanding_amount,
                    ]
                )
            );
    }

    private function paymentEvents(
        Client $client
    ): Collection {
        return PaymentAllocation::query()
            ->whereHas(
                'invoice',
                fn ($query) => $query->where(
                    'client_id',
                    $client->id
                )
            )
            ->whereIn(
                'status',
                [
                    'approved',
                    'imported',
                ]
            )
            ->with([
                'transaction',
                'invoice',
            ])
            ->get()
            ->map(
                function (PaymentAllocation $allocation): TimelineEvent {
                    $occurredAt =
                        $allocation->approved_at
                        ?? $allocation->transaction?->transaction_date
                        ?? $allocation->created_at;

                    return new TimelineEvent(
                        occurredAt: Carbon::parse(
                            $occurredAt
                        ),

                        type: 'payment',

                        title: sprintf(
                            'Payment allocated to invoice %s',
                            $allocation->invoice?->invoice_number
                                ?? 'unknown'
                        ),

                        description: sprintf(
                            '£%s was allocated against invoice %s.',
                            number_format(
                                (float) $allocation->amount,
                                2
                            ),
                            $allocation->invoice?->invoice_number
                                ?? 'unknown'
                        ),

                        value: (float) $allocation->amount,

                        importance: 85,

                        identity: 'payment_allocation:'.$allocation->id,

                        metadata: [
                            'payment_allocation_id' => $allocation->id,

                            'bank_transaction_id' => $allocation
                                ->bank_transaction_id,

                            'accounting_invoice_id' => $allocation
                                ->accounting_invoice_id,

                            'invoice_number' => $allocation
                                ->invoice?->invoice_number,

                            'status' => $allocation->status,

                            'confidence' => $allocation->confidence !== null
                                ? (float) $allocation->confidence
                                : null,

                            'match_method' => $allocation->match_method,
                        ]
                    );
                }
            );
    }

    private function decisionEvents(
        Client $client
    ): Collection {
        return BusinessDecisionOutcome::query()
            ->where(
                'client_id',
                $client->id
            )
            ->whereNotNull(
                'decided_at'
            )
            ->get()
            ->map(
                fn (BusinessDecisionOutcome $outcome) => new TimelineEvent(
                    occurredAt: Carbon::parse(
                        $outcome->completed_at
                        ?? $outcome->decided_at
                    ),

                    type: 'decision',

                    title: sprintf(
                        '%s recommendation %s',
                        ucfirst(
                            $outcome->decision_type
                        ),
                        $outcome->status
                    ),

                    description: $outcome->outcome
                        ?? $outcome->reason
                        ?? $outcome->action,

                    value: $outcome->financial_result
                        ?? $outcome->value,

                    importance: $outcome->priority,

                    identity: 'business_decision_outcome:'.$outcome->id,

                    metadata: [
                        'outcome_id' => $outcome->id,

                        'decision_type' => $outcome->decision_type,

                        'status' => $outcome->status,

                        'action' => $outcome->action,
                    ]
                )
            );
    }

    private function memoryEvents(
        Client $client
    ): Collection {
        return BusinessMemoryEvent::query()
            ->where(
                'client_id',
                $client->id
            )
            ->get()
            ->map(
                fn (BusinessMemoryEvent $event) => new TimelineEvent(
                    occurredAt: Carbon::parse(
                        $event->occurred_at
                    ),

                    type: 'memory',

                    title: $event->title,

                    description: $event->description,

                    value: $event->value,

                    importance: 80,

                    identity: $event->source_type
                        && $event->source_id
                            ? $event->source_type.':'.$event->source_id
                            : 'memory:'.$event->id,

                    metadata: [
                        'memory_event_id' => $event->id,

                        'source_type' => $event->source_type,

                        'source_id' => $event->source_id,
                    ]
                )
            );
    }
}
