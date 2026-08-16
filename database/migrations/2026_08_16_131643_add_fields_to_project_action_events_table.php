<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_action_events', function (Blueprint $table) {
            $table->foreignId('project_action_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->json('payload')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_action_events', function (Blueprint $table) {
            $table->dropForeign([
                'project_action_id',
            ]);

            $table->dropColumn([
                'project_action_id',
                'type',
                'payload',
            ]);
        });
    }
};
