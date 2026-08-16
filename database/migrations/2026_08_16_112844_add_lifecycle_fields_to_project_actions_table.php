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
        Schema::table('project_actions', function (Blueprint $table) {
            $table->string('owner_type')
                ->nullable()
                ->after('assigned_to');

            $table->timestamp('verified_at')
                ->nullable()
                ->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_actions', function (Blueprint $table) {
            $table->dropColumn([
                'owner_type',
                'verified_at',
            ]);
        });
    }
};
