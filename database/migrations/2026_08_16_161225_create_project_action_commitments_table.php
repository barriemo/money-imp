<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_action_commitments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_action_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('owner');

            $table->string('status')
                ->default('open');

            $table->date('due_date')
                ->nullable();

            $table->timestamp('committed_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_action_commitments');
    }
};
