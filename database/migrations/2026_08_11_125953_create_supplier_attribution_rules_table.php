<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_attribution_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('supplier_profile_id')
                ->constrained('supplier_profiles')
                ->cascadeOnDelete();

            $table->string('match_type')
                ->default('contains');

            $table->string('match_value');

            $table->string('purpose');

            $table->foreignUuid('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->unsignedTinyInteger('confidence')
                ->default(100);

            $table->boolean('apply_historically')
                ->default(true);

            $table->boolean('active')
                ->default(true);

            $table->timestamp('last_applied_at')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'supplier_profile_id',
                'match_type',
                'match_value',
                'purpose',
                'client_id',
            ], 'supplier_rule_unique');

            $table->index([
                'active',
                'purpose',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_attribution_rules'
        );
    }
};
