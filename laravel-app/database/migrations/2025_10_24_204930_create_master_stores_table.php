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
        Schema::create('master_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('master_series')->onDelete('cascade'); // 系列テーブル参照
            $table->string('name'); // 店舗名
            $table->string('short_name'); // 簡略名
            $table->text('details')->nullable(); // 店舗詳細
            $table->string('specific_date')->nullable(); // 特定日
            $table->string('anniversary_date')->nullable(); // 周年日
            $table->boolean('is_collected')->default(true); // 回収フラグ
            $table->timestamps();

            $table->unique(['series_id', 'name']); // 系列名と店舗名の複合一意制約
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_stores');
    }
};
