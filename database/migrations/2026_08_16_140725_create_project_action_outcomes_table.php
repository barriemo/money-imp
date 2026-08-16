<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_action_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_action_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->text('description');

            $table->string('metric')->nullable();

            $table->string('value')->nullable();

            $table->unsignedInteger('confidence')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_action_outcomes');
    }
};
