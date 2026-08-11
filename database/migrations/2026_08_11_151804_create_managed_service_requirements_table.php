<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'managed_service_requirements',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'managed_service_template_id'
                )
                    ->constrained(
                        'managed_service_templates'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'component_type'
                );

                $table->string(
                    'name'
                );

                $table->boolean(
                    'required'
                )->default(true);

                $table->unsignedInteger(
                    'minimum_count'
                )->default(1);

                $table->unsignedInteger(
                    'weight'
                )->default(1);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'managed_service_template_id',
                    'component_type',
                ], 'managed_service_requirement_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'managed_service_requirements'
        );
    }
};
