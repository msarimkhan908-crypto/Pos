<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory module was removed: products are catalog-only (price, variants, modifiers).
 * Drops the stock ledger and the stock columns added in the Day 2 migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['track_inventory', 'stock_quantity', 'min_stock']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }

    /**
     * Restores the structure only; stock data is not recoverable.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_inventory')->default(true)->after('discount_value');
            $table->integer('stock_quantity')->default(0)->after('track_inventory');
            $table->unsignedInteger('min_stock')->default(0)->after('stock_quantity');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->after('selling_price');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->integer('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['product_id', 'product_variant_id', 'created_at']);
        });
    }
};
