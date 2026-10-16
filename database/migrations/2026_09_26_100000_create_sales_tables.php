<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS sales. Sales do not change stock (there is no inventory module).
 *
 * A held sale is a `sales` row with status "held", no invoice number and no payments.
 * Names and prices are snapshotted on each line, so later catalog changes never alter a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Gap-free counters (e.g. invoice numbers), incremented under a row lock.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('item_discount', 14, 2)->default(0);
            $table->string('order_discount_type', 10)->nullable();
            $table->decimal('order_discount_value', 14, 2)->default(0);
            $table->decimal('order_discount', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('payment_method', 10)->nullable();
            $table->decimal('cash_received', 14, 2)->nullable();
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'completed_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->string('variant_name', 100)->nullable();
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('modifiers_total', 14, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('order_discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index(['product_id', 'variant_id']);
        });

        Schema::create('sale_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 100);
            $table->string('name', 100);
            $table->decimal('price', 12, 2);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 10)->index();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_item_modifiers');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('number_sequences');
    }
};
