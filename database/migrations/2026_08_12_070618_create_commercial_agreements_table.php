<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commercial_agreements',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('client_id')
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->string('service_type');

                $table->string('service_key')->nullable();

                $table->string('cadence');
                $table->string('status')->default('candidate');

                $table->decimal('observed_value', 12, 2)->default(0);
                $table->decimal('monthly_equivalent', 12, 2)->default(0);

                $table->unsignedTinyInteger('confidence')->default(50);

                $table->date('starts_on')->nullable();
                $table->date('renews_on')->nullable();

                $table->string('source')->default('commercial_inference');

                $table->text('reason')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'service_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'commercial_agreements'
        );
    }
};
