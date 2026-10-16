<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kot_number is derived from order_no which resets daily, so KOT-1 repeats
 * every business day. Replace the global unique on kot_number with a
 * composite unique on (business_date, kot_number).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->dropUnique(['kot_number']);
            $table->unique(['business_date', 'kot_number'], 'kitchen_orders_business_date_kot_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->dropUnique('kitchen_orders_business_date_kot_number_unique');
            $table->unique('kot_number');
        });
    }
};
