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
        Schema::create('minrepo_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('master_stores')->onDelete('cascade'); // 店舗ID
            $table->foreignId('model_id')->constrained('master_models')->onDelete('cascade'); // 機種ID
            $table->date('date'); // 日付
            $table->integer('machine_number'); // 台番号
            $table->integer('differential_medals')->nullable(); // 差枚 ("-"の場合はnull)
            $table->integer('game_count'); // ゲーム数
            $table->decimal('payout_rate', 5, 2)->nullable(); // 出率 (例: 123.45%)
            $table->timestamps();

            // 複合ユニークキー: 同じ店舗・機種・日付・台番号の重複を防ぐ
            $table->unique(['store_id', 'model_id', 'date', 'machine_number'], 'minrepo_data_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minrepo_data');
    }
};
