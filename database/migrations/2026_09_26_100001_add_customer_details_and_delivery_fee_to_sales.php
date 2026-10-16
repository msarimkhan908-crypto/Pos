<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer details typed at the POS (snapshotted on the sale, like line names and prices)
 * and a delivery fee added to the sale total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_name', 150)->nullable()->after('customer_id');
            $table->string('customer_phone', 30)->nullable()->index()->after('customer_name');
            $table->text('customer_address')->nullable()->after('customer_phone');
            $table->decimal('delivery_fee', 14, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['customer_phone']);
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_address', 'delivery_fee']);
        });
    }
};
