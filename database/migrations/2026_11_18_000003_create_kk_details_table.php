<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the kk_details table storing all rendered fields (family card number, names,
     * address as json, members as json, issued info, signatory info), plus original raw AI output,
     * audit data, and manual correction tracking (is_edited, is_submitted, last_edited_at).
     */
    public function up(): void
    {
        Schema::create('kk_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->nullable()->constrained('kk_submissions')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('kk_batches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ── File & Header Identity ──
            $table->string('original_filename')->nullable();
            $table->string('family_card_number', 30)->index();
            $table->string('head_of_family');
            $table->string('head_of_family_japan')->nullable();

            // ── Alamat Lengkap & Anggota (JSON Structure) ──
            $table->json('address');
            $table->json('members');

            // ── Penerbitan & Tanda Tangan ──
            $table->string('issued_date', 50)->nullable();
            $table->string('issued_place', 100)->nullable();
            $table->string('signatory_officer')->nullable();
            $table->string('signatory_nip', 50)->nullable();

            // ── AI Audit, Dual-Language Logging & Verification ──
            $table->json('raw_gemini_output')->nullable();
            $table->json('original_data')->nullable();
            $table->json('translated_data')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_submitted')->default(false)->index();
            $table->timestamp('last_edited_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kk_details');
    }
};
