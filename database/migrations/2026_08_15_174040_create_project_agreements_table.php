<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_agreements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('scope');

            $table->json('included_deliverables')
                ->nullable();

            $table->text('excluded_scope')
                ->nullable();

            $table->string('approved_by')
                ->nullable();

            $table->timestamp('approved_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_agreements');
    }
};
