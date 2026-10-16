<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150)->index();

            // Unique across products and variants (enforced in validation); nullable barcodes may repeat as NULL.
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();

            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);

            $table->boolean('track_inventory')->default(true);
            // Cached balance. Written only by InventoryService together with a stock_movements row.
            // Unused (kept at 0) when the product has variants; stock then lives on each variant.
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('min_stock')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
