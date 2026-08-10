<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('provider');
            $table->string('name');

            $table->string('status')->default('disconnected');

            $table->text('external_account_id')->nullable();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();

            $table->text('last_error')->nullable();

            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'name']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_connections');
    }
};
