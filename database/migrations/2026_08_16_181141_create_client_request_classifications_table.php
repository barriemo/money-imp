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
        Schema::create('client_request_classifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('classification');

            $table->unsignedInteger('confidence');

            $table->text('reason');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_request_classifications');
    }
};
