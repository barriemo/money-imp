<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex(
            'executive_actions',
            'executive_actions_fingerprint_unique'
        )) {
            Schema::table('executive_actions', function (Blueprint $table) {
                $table->unique(
                    'fingerprint',
                    'executive_actions_fingerprint_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(
            'executive_actions',
            'executive_actions_fingerprint_unique'
        )) {
            Schema::table('executive_actions', function (Blueprint $table) {
                $table->dropUnique(
                    'executive_actions_fingerprint_unique'
                );
            });
        }
    }
};
