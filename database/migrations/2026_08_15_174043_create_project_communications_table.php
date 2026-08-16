<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_communications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('direction')
                ->default('internal');

            $table->text('summary');

            $table->text('commitment')
                ->nullable();

            $table->text('impact')
                ->nullable();

            $table->text('decision')
                ->nullable();

            $table->string('requested_by')
                ->nullable();

            $table->timestamp('occurred_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_communications');
    }
};
