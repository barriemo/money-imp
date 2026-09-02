<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commercial_evidence_allocation_sets',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                /*
                 * The human structural review which established
                 * whether this exact evidence is a bundle or
                 * requires a monetary split.
                 */
                $table->uuid(
                    'composite_commercial_review_id'
                );

                /*
                 * Immutable source financial evidence.
                 */
                $table->uuid(
                    'accounting_invoice_item_id'
                );

                $table->uuid('client_id');

                /*
                 * Exact material source state approved for
                 * allocation.
                 */
                $table->string(
                    'evidence_fingerprint',
                    64
                );

                /*
                 * bundle
                 * split
                 */
                $table->enum(
                    'allocation_kind',
                    [
                        'bundle',
                        'split',
                    ]
                );

                /*
                 * Integer minor units deliberately avoid floating
                 * point conservation errors.
                 */
                $table->bigInteger(
                    'source_net_pence'
                );

                $table
                    ->foreignId('allocated_by')
                    ->nullable();

                $table->timestamp(
                    'allocated_at'
                );

                $table
                    ->text('reason')
                    ->nullable();

                /*
                 * Freeze the exact source + target state seen by
                 * the allocator.
                 */
                $table->json(
                    'allocation_snapshot'
                );

                $table->timestamps();

                /*
                 * One approved monetary interpretation for one
                 * exact terminal structural review.
                 */
                $table->unique(
                    'composite_commercial_review_id',
                    'cea_set_review_unique'
                );

                /*
                 * The same exact source evidence state cannot be
                 * allocated twice through another review.
                 */
                $table->unique(
                    [
                        'accounting_invoice_item_id',
                        'evidence_fingerprint',
                    ],
                    'cea_set_evidence_unique'
                );

                $table->index(
                    [
                        'client_id',
                        'allocated_at',
                    ],
                    'cea_set_client_history'
                );

                $table->foreign(
                    'composite_commercial_review_id',
                    'cea_set_review_fk'
                )
                    ->references('id')
                    ->on('composite_commercial_reviews')
                    ->restrictOnDelete();

                $table->foreign(
                    'accounting_invoice_item_id',
                    'cea_set_item_fk'
                )
                    ->references('id')
                    ->on('accounting_invoice_items')
                    ->restrictOnDelete();

                $table->foreign(
                    'client_id',
                    'cea_set_client_fk'
                )
                    ->references('id')
                    ->on('clients')
                    ->restrictOnDelete();

                $table->foreign(
                    'allocated_by',
                    'cea_set_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'commercial_evidence_allocations',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid(
                    'allocation_set_id'
                );

                $table->uuid(
                    'client_service_id'
                );

                /*
                 * Signed integer pence.
                 *
                 * Sum of every line in one set must equal the
                 * source set's source_net_pence exactly.
                 */
                $table->bigInteger(
                    'allocated_net_pence'
                );

                $table->timestamps();

                /*
                 * A service may occur only once within a set.
                 */
                $table->unique(
                    [
                        'allocation_set_id',
                        'client_service_id',
                    ],
                    'cea_line_service_unique'
                );

                $table->index(
                    'client_service_id',
                    'cea_line_service_lookup'
                );

                $table->foreign(
                    'allocation_set_id',
                    'cea_line_set_fk'
                )
                    ->references('id')
                    ->on(
                        'commercial_evidence_allocation_sets'
                    )
                    ->restrictOnDelete();

                /*
                 * Allocation history is commercial audit history.
                 * Do not erase it because a service is later
                 * removed.
                 */
                $table->foreign(
                    'client_service_id',
                    'cea_line_service_fk'
                )
                    ->references('id')
                    ->on('client_services')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'commercial_evidence_allocations'
        );

        Schema::dropIfExists(
            'commercial_evidence_allocation_sets'
        );
    }
};
