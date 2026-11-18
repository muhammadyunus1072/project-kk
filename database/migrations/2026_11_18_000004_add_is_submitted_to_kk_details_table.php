<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds is_submitted column to kk_details table and makes submission_id and batch_id
     * nullable to support manual inputs without uploaded file attachments.
     */
    public function up(): void
    {
        Schema::table('kk_details', function (Blueprint $table) {
            $table->boolean('is_submitted')->default(false)->index()->after('is_edited');
            $table->ulid('submission_id')->nullable()->change();
            $table->ulid('batch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kk_details', function (Blueprint $table) {
            $table->dropIndex(['is_submitted']);
            $table->dropColumn('is_submitted');
            $table->ulid('submission_id')->nullable(false)->change();
            $table->ulid('batch_id')->nullable(false)->change();
        });
    }
};
