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
        Schema::table('executive_actions', function (Blueprint $table) {
            $table->foreignId('capability_definition_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('executive_actions', function (Blueprint $table) {
            $table->dropForeign([
                'capability_definition_id',
            ]);

            $table->dropColumn(
                'capability_definition_id'
            );
        });
    }
};
