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
        Schema::create('master_models', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 機種名
            $table->foreignId('category_id') // 外部キー (機種分類テーブルへの参照)
                ->constrained('master_models_categories') // master_models_categoriesテーブルを参照
                ->onUpdate('cascade') // 外部キーの更新時
                ->onDelete('restrict'); // 外部キーの削除時
            $table->text('details')->nullable(); // 機種詳細
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_models');
    }
};
