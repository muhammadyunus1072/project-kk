<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the kk_batches table that tracks a group of KK image uploads for an authenticated user.
     */
    public function up(): void
    {
        Schema::create('kk_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('total_files');
            $table->unsignedSmallInteger('completed')->default(0);
            $table->unsignedSmallInteger('failed')->default(0);
            $table->enum('status', ['pending', 'processing', 'done', 'partial_failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kk_batches');
    }
};
