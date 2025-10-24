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
        Schema::create('process_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('process_date');
            $table->char('status', 20)->default('pending'); // pending, processing, error, processed
            $table->timestamps();

            $table->unique(['store_id', 'process_date']);
            $table->foreign('store_id')->references('id')->on('master_stores')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_logs');
    }
};
