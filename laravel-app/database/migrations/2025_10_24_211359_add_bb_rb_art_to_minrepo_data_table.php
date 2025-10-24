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
        Schema::table('minrepo_data', function (Blueprint $table) {
            $table->integer('bb_count')->nullable()->after('payout_rate'); // BB回数
            $table->integer('rb_count')->nullable()->after('bb_count'); // RB回数
            $table->integer('art_count')->nullable()->after('rb_count'); // ART回数
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minrepo_data', function (Blueprint $table) {
            $table->dropColumn(['bb_count', 'rb_count', 'art_count']);
        });
    }
};
