<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the kk_submissions table with attachment columns matching gensen attachment style:
     * disk, path, original_filename, mimetype, filesize, checksum
     */
    public function up(): void
    {
        Schema::create('kk_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('kk_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Attachment core metadata
            $table->string('disk')->default('supabase');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mimetype', 100);
            $table->unsignedBigInteger('filesize');
            $table->string('checksum', 64); // SHA-256

            // Extraction processing state
            $table->string('status', 30)->default('pending');
            $table->json('extracted_data')->nullable(); // TranslationResult JSON
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kk_submissions');
    }
};
