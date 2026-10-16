<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'device_id')) {
                $table->string('device_id', 20)->nullable()->after('id');
            }
            if (! Schema::hasColumn('sales', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['device_id', 'synced_at']);
        });
    }
};
