<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_decision_outcomes', function (Blueprint $table) {
            $table->string('fingerprint', 64)
                ->nullable()
                ->after('id');

            $table->index([
                'fingerprint',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('business_decision_outcomes', function (Blueprint $table) {
            $table->dropIndex([
                'fingerprint',
                'status',
            ]);

            $table->dropColumn(
                'fingerprint'
            );
        });
    }
};
