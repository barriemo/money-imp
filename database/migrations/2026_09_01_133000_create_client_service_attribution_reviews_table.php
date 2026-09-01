<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'client_service_attribution_reviews',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('client_id');

                $table->uuid(
                    'client_service_id'
                );

                $table->string(
                    'candidate_fingerprint',
                    64
                );

                /*
                 * Hash of the exact sorted invoice-item IDs
                 * reviewed by the human.
                 */
                $table->string(
                    'evidence_fingerprint',
                    64
                );

                /*
                 * approved
                 * rejected
                 */
                $table->string(
                    'decision'
                );

                /*
                 * Users use bigint IDs.
                 *
                 * Nullable permits nullOnDelete while the service
                 * still requires a real reviewer when writing.
                 */
                $table
                    ->foreignId('reviewed_by')
                    ->nullable();

                $table
                    ->timestamp('reviewed_at');

                $table
                    ->text('reason')
                    ->nullable();

                $table->json(
                    'candidate_snapshot'
                );

                $table->timestamps();

                $table
                    ->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->cascadeOnDelete();

                $table
                    ->foreign('client_service_id')
                    ->references('id')
                    ->on('client_services')
                    ->cascadeOnDelete();

                $table
                    ->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'client_id',
                        'candidate_fingerprint',
                        'evidence_fingerprint',
                    ],
                    'client_service_attribution_review_evidence'
                );

                $table->index(
                    [
                        'client_service_id',
                        'decision',
                    ],
                    'client_service_attribution_review_service'
                );

                $table->index(
                    [
                        'decision',
                        'reviewed_at',
                    ],
                    'client_service_attribution_review_status'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_service_attribution_reviews'
        );
    }
};
