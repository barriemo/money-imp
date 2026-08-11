<?php

namespace App\Domains\Infrastructure\Attribution;

use App\Domains\Attribution\AttributionResolver;
use App\Models\HostingAttributionCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HostingAttributionEngine
{
    public function __construct(
        private HostingEvidenceBuilder $evidence,
        private AttributionResolver $resolver
    ) {}

    public function candidates(): Collection
    {
        return DB::table(
            'accounting_invoice_items as items'
        )
            ->join(
                'accounting_invoices as invoices',
                'invoices.id',
                '=',
                'items.accounting_invoice_id'
            )
            ->join(
                'clients',
                'clients.id',
                '=',
                'invoices.client_id'
            )
            ->select([
                'items.id as invoice_item_id',
                'items.description',
                'items.unit_price',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'clients.id as client_id',
                'clients.name as client_name',
            ])
            ->orderByDesc(
                'invoices.invoice_date'
            )
            ->get()
            ->map(
                function (
                    object $item
                ): ?HostingAttributionCandidate {
                    $evidence =
                        $this->evidence
                            ->build(
                                $item
                            );

                    if (! $evidence) {
                        return null;
                    }

                    $this->resolver->propose(
                        subjectType: 'client',

                        subjectId: $item->client_id,

                        relationshipType: 'hosted_on',

                        targetType: 'supplier_asset',

                        targetId: null,

                        source: 'hosting_invoice_history',

                        reason: 'Recurring hosting billing exists but the underlying server is not yet attributed.',

                        evidence: [
                            [
                                'type' => 'invoice_history',

                                'summary' => $item->description,

                                'confidence' => 95,

                                'reference' => $item->invoice_item_id,

                                'metadata' => [
                                    'invoice_number' => $item->invoice_number,

                                    'invoice_date' => $item->invoice_date,

                                    'monthly_rate' => (float) $item->unit_price,
                                ],
                            ],
                        ],

                        metadata: [
                            'service_hint' => $evidence['service_hint'],

                            'monthly_rate' => $evidence['monthly_rate'],
                        ]
                    );

                    return HostingAttributionCandidate::updateOrCreate(
                        [
                            'client_id' => $item->client_id,

                            'accounting_invoice_item_id' => $item->invoice_item_id,

                            'relationship_type' => 'hosts',
                        ],
                        [
                            'confidence' => 95,

                            'status' => 'candidate',

                            'source' => 'invoice_history',

                            'reason' => 'Recurring client invoice contains a hosting service but the underlying infrastructure is not yet attributed.',

                            'evidence' => [
                                ...$evidence,

                                'invoice_number' => $item->invoice_number,

                                'invoice_date' => $item->invoice_date,

                                'client_name' => $item->client_name,
                            ],
                        ]
                    );
                }
            )
            ->filter()
            ->values();
    }
}
