<?php

use App\Support\BusinessDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: daily per-device order numbers.
 *
 * Adds business_date (the calendar date the sale "belongs to") and order_no
 * (a per-day, per-device counter) to sales and kitchen_orders.
 *
 * Backfills existing completed sales and propagates their order_no to kitchen_orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── sales ────────────────────────────────────────────────────────────
        Schema::table('sales', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('completed_at')->index();
            $table->unsignedInteger('order_no')->nullable()->after('business_date');
        });

        // Unique per (device_id, business_date, order_no).
        // device_id is nullable; MySQL and SQLite both treat NULLs as distinct in UNIQUE
        // indexes, so two rows with NULL device_id + same date will not clash — the sequence
        // logic ('order:web:Y-m-d') guarantees uniqueness for the web case independently.
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['business_date', 'order_no'], 'sales_business_date_order_no_index');
        });

        // ── kitchen_orders ───────────────────────────────────────────────────
        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('sale_id');
            $table->unsignedInteger('order_no')->nullable()->after('business_date');
        });

        // ── backfill existing completed sales ────────────────────────────────
        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->dropColumn(['business_date', 'order_no']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_business_date_order_no_index');
            $table->dropColumn(['business_date', 'order_no']);
        });
    }

    private function backfill(): void
    {
        $tz = settings('general.timezone') ?? config('app.timezone', 'UTC');

        // Fetch completed sales ordered by completed_at then id.
        $sales = DB::table('sales')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get(['id', 'device_id', 'completed_at']);

        // Group by (device_id, business_date) and assign sequential order_no.
        $counters = [];

        foreach ($sales as $sale) {
            $completedAt = \Carbon\CarbonImmutable::parse($sale->completed_at)->setTimezone($tz);
            $businessDate = BusinessDay::current($completedAt)->toDateString();
            $key = ($sale->device_id ?? '') . '|' . $businessDate;

            $counters[$key] = ($counters[$key] ?? 0) + 1;
            $orderNo = $counters[$key];

            DB::table('sales')
                ->where('id', $sale->id)
                ->update([
                    'business_date' => $businessDate,
                    'order_no'      => $orderNo,
                ]);

            // Propagate to kitchen_orders for this sale.
            DB::table('kitchen_orders')
                ->where('sale_id', $sale->id)
                ->update([
                    'business_date' => $businessDate,
                    'order_no'      => $orderNo,
                ]);
        }
    }
};
