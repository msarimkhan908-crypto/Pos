<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: audit trail for bill edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 500);
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at')->nullable();
        });

        // Version column for sync re-push detection.
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedSmallInteger('edit_version')->default(0)->after('return_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('edit_version');
        });
        Schema::dropIfExists('sale_edits');
    }
};
