<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'receipt.customer')->first();

        if ($row) {
            $data = json_decode($row->value, true) ?? [];
            $data['show_borders'] = true;
            DB::table('settings')
                ->where('key', 'receipt.customer')
                ->update(['value' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        $row = DB::table('settings')->where('key', 'receipt.customer')->first();

        if ($row) {
            $data = json_decode($row->value, true) ?? [];
            $data['show_borders'] = false;
            DB::table('settings')
                ->where('key', 'receipt.customer')
                ->update(['value' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        }
    }
};
