<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'license_key')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('license_key', 64)->unique()->nullable()->after('slug');
            });
        }

        // Back-fill any orgs that don't have a license key yet
        DB::table('organizations')
            ->whereNull('license_key')
            ->orderBy('id')
            ->each(function ($org) {
                DB::table('organizations')
                    ->where('id', $org->id)
                    ->update(['license_key' => Str::random(40)]);
            });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('license_key');
        });
    }
};
