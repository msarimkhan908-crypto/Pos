<?php

namespace Database\Seeders;

use App\Models\KitchenStation;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

/**
 * Starter restaurant setup: the usual kitchen stations and Tables 1–12.
 * Idempotent (existing names are left alone). Run on demand:
 *
 *     php artisan db:seed --class=RestaurantSeeder
 */
class RestaurantSeeder extends Seeder
{
    public const STATIONS = ['Main Kitchen', 'Grill', 'Pizza', 'Drinks', 'Dessert'];

    public const TABLES = 12;

    public function run(): void
    {
        foreach (self::STATIONS as $order => $name) {
            KitchenStation::firstOrCreate(['name' => $name], ['sort_order' => $order]);
        }

        for ($number = 1; $number <= self::TABLES; $number++) {
            RestaurantTable::firstOrCreate(['name' => "Table {$number}"], ['capacity' => 4, 'sort_order' => $number]);
        }
    }
}
