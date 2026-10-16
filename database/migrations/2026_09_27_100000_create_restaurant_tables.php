<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 5: dining tables, order types, kitchen stations and kitchen order tickets (KOTs).
 *
 * A completed sale with kitchen items produces a KOT (kitchen_orders) whose lines (kot_items)
 * point at the sale lines and carry their kitchen station, so each station can work on its
 * own items. A sale can have many KOTs (e.g. items ordered later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('section', 50)->nullable();
            $table->unsignedTinyInteger('capacity')->nullable();
            $table->string('status', 20)->default('available')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sort_order', 'name']);
        });

        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('sort_order')->constrained()->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('order_type', 20)->default('take_away')->after('status')->index();
            $table->foreignId('table_id')->nullable()->after('order_type')->constrained('restaurant_tables')->restrictOnDelete();
            $table->text('order_note')->nullable()->after('customer_address');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('note', 200)->nullable()->after('sku');
        });

        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->id();
            $table->string('kot_number', 20)->unique();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->string('order_type', 20);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('kot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('notes', 200)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['kitchen_station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kot_items');
        Schema::dropIfExists('kitchen_orders');

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_id');
            $table->dropIndex(['order_type']);
            $table->dropColumn(['order_type', 'order_note']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_station_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_station_id');
        });

        Schema::dropIfExists('kitchen_stations');
        Schema::dropIfExists('restaurant_tables');
    }
};
