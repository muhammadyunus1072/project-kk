<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds original_data (Indonesian Latin) and translated_data (Japanese Katakana/Kanji)
     * to kk_details for comprehensive audit history and dual-language logging.
     */
    public function up(): void
    {
        Schema::table('kk_details', function (Blueprint $table) {
            $table->json('original_data')->nullable()->after('raw_gemini_output');
            $table->json('translated_data')->nullable()->after('original_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kk_details', function (Blueprint $table) {
            $table->dropColumn(['original_data', 'translated_data']);
        });
    }
};
