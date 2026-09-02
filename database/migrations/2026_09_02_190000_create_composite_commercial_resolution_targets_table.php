<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'composite_commercial_resolution_targets',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                /*
                 * Structural human decision being completed.
                 */
                $table->uuid(
                    'composite_commercial_review_id'
                );

                /*
                 * Monetary interpretation created by the existing
                 * allocation ledger.
                 *
                 * The resolution ledger does not become a second
                 * monetary authority.
                 */
                $table->uuid(
                    'allocation_set_id'
                );

                $table->uuid(
                    'commercial_evidence_allocation_id'
                );

                $table->uuid('client_id');

                $table->uuid(
                    'client_service_id'
                );

                /*
                 * Human target-selection action:
                 *
                 * created
                 * existing
                 * reactivated
                 */
                $table->enum(
                    'target_action',
                    [
                        'created',
                        'existing',
                        'reactivated',
                    ]
                );

                /*
                 * Null only when a new canonical service was created.
                 */
                $table->enum(
                    'previous_service_status',
                    [
                        'active',
                        'historical',
                    ]
                )->nullable();

                $table->enum(
                    'resulting_service_status',
                    [
                        'active',
                        'historical',
                    ]
                );

                $table->bigInteger(
                    'allocated_net_pence'
                );

                $table
                    ->foreignId('resolved_by')
                    ->nullable();

                $table->timestamp(
                    'resolved_at'
                );

                $table
                    ->text('reason')
                    ->nullable();

                /*
                 * Frozen target/status/allocation state observed when
                 * the human completed the composite interpretation.
                 */
                $table->json(
                    'resolution_snapshot'
                );

                $table->timestamps();

                $table
                    ->foreign(
                        'composite_commercial_review_id'
                    )
                    ->references('id')
                    ->on('composite_commercial_reviews')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'allocation_set_id'
                    )
                    ->references('id')
                    ->on(
                        'commercial_evidence_allocation_sets'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'commercial_evidence_allocation_id'
                    )
                    ->references('id')
                    ->on(
                        'commercial_evidence_allocations'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'client_service_id'
                    )
                    ->references('id')
                    ->on('client_services')
                    ->restrictOnDelete();

                $table
                    ->foreign('resolved_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Each monetary allocation line receives exactly one
                 * target-resolution audit record.
                 */
                $table->unique(
                    'commercial_evidence_allocation_id',
                    'ccrt_allocation_unique'
                );

                /*
                 * One reviewed evidence state cannot resolve twice
                 * to the same canonical target.
                 *
                 * Split reviews may legitimately have several
                 * different target services.
                 */
                $table->unique(
                    [
                        'composite_commercial_review_id',
                        'client_service_id',
                    ],
                    'ccrt_review_service_unique'
                );

                $table->index(
                    [
                        'composite_commercial_review_id',
                        'resolved_at',
                    ],
                    'ccrt_review_history'
                );

                $table->index(
                    [
                        'client_id',
                        'client_service_id',
                    ],
                    'ccrt_client_service'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'composite_commercial_resolution_targets'
        );
    }
};
