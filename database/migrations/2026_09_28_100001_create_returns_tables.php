<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales returns. A return refunds items of a completed sale; it never changes stock
 * (there is no inventory module). sale_items.returned_quantity and sales.return_status /
 * returned_amount are kept in step by App\Services\ReturnService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 20)->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->string('refund_method', 10);
            $table->string('reason', 100);
            $table->string('status', 20)->index();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'return_date']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('refund_amount', 14, 2);
            $table->string('reason', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('return_status', 20)->default('none')->after('status')->index();
            $table->decimal('returned_amount', 14, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['return_status']);
            $table->dropColumn(['return_status', 'returned_amount']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });

        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
