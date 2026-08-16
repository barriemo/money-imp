<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_update_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('requested_from')
                ->nullable();

            $table->string('reason');

            $table->string('status')
                ->default('open');

            $table->text('response')
                ->nullable();

            $table->timestamp('requested_at')
                ->nullable();

            $table->timestamp('responded_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_update_requests');
    }
};
