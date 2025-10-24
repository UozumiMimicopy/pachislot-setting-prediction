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
        Schema::create('master_models_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 分類名 (6.5, スマスロなど)
            $table->text('description')->nullable(); // 分類の解説文
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_models_categories');
    }
};
