<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commercial_agreement_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('commercial_agreement_id')
                    ->constrained('commercial_agreements')
                    ->cascadeOnDelete();

                $table->string('type');

                $table->string('reference')->nullable();

                $table->text('summary');

                $table->date('observed_on')->nullable();

                $table->decimal('observed_value', 12, 2)->nullable();

                $table->unsignedTinyInteger('confidence')->default(50);

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index([
                    'commercial_agreement_id',
                    'type',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'commercial_agreement_evidence'
        );
    }
};
