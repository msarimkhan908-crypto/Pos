<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds organization_id to every tenant-owned table so each restaurant's data
 * is isolated. Also adjusts unique constraints that were previously global
 * (e.g. invoice_number, sku) to be scoped per organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite limitation: cannot add NOT NULL columns to existing tables without a default.
        // We use nullable() here; the BelongsToOrganization trait always fills organization_id on create.
        $tenantTables = [
            'categories', 'brands', 'modifier_groups',
            'customers', 'suppliers',
            'kitchen_stations', 'kitchen_orders',
            'expenses', 'expense_categories',
        ];

        foreach ($tenantTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $t->index('organization_id');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // SKU unique is now per-org (enforced in app validation)
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->index('organization_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->index('organization_id');
        });

        // ── Sales ─────────────────────────────────────────────────────────────
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Invoice number unique is now per-org
            $table->dropUnique(['invoice_number']);
            $table->unique(['organization_id', 'invoice_number']);
            $table->index('organization_id');
        });

        // ── Restaurant tables ─────────────────────────────────────────────────
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['name']);
            $table->unique(['organization_id', 'name']);
            $table->index('organization_id');
        });

        // ── Settings (per-org key-value store) ────────────────────────────────
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['key']);
            $table->unique(['organization_id', 'key']);
            $table->index('organization_id');
        });

        // ── Number sequences (invoice counters per org) ───────────────────────
        // Drop existing primary key (string name) and replace with composite (org_id, name)
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropPrimary();
            $table->unsignedBigInteger('organization_id')->nullable()->after('name');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->primary(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->primary('name');
        });

        foreach (['expense_categories', 'expenses', 'kitchen_orders', 'kitchen_stations', 'customers', 'suppliers'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropUnique(['organization_id', 'key']);
            $table->unique('key');
            $table->dropColumn('organization_id');
        });

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['organization_id', 'name']);
            $table->unique('name');
            $table->dropColumn('organization_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['organization_id', 'invoice_number']);
            $table->unique('invoice_number');
            $table->dropColumn('organization_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->unique('sku');
            $table->unique('barcode');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->unique('sku');
            $table->unique('barcode');
        });

        foreach (['modifier_groups', 'brands', 'categories'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
    }
};
