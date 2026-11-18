<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the kk_details table storing all rendered fields (family card number, names,
     * address as json, members as json, issued info, signatory info), plus original raw AI output
     * and manual correction tracking (is_edited, last_edited_at).
     */
    public function up(): void
    {
        Schema::create('kk_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('kk_submissions')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('kk_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ── Primary Header & Identity ──
            $table->string('family_card_number', 30)->index();   // No. KK (16 digit)
            $table->string('head_of_family');                     // Nama Latin Kepala Keluarga
            $table->string('head_of_family_japan')->nullable();   // Nama Katakana Kepala Keluarga

            // ── Alamat Lengkap (JSON structure) ──
            // { street, rtRw, subDistrict, district, regencyCity, province, postalCode }
            $table->json('address');

            // ── Anggota Keluarga (JSON array of all members) ──
            // [ { no, fullNameOriginal, fullNameKatakana, nik, gender, birthPlace, birthDate,
            //     religion, education, occupation, maritalStatus, relationStatus,
            //     fatherName, motherName, passportNumber, stayPermitNumber, nationality } ]
            $table->json('members');

            // ── Penerbitan & Tanda Tangan ──
            $table->string('issued_date', 50)->nullable();       // e.g. "2021年05月18日"
            $table->string('issued_place', 100)->nullable();     // e.g. "南ジャカルタ市"
            $table->string('signatory_officer')->nullable();     // Nama Kadisdukcapil
            $table->string('signatory_nip', 50)->nullable();      // NIP Kadisdukcapil

            // ── AI Audit & Manual Correction Tracking ──
            $table->json('raw_gemini_output')->nullable();       // Backup of original AI output
            $table->boolean('is_edited')->default(false);         // True if user edited manually
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
