<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free counters for document numbers (table `number_sequences`).
 *
 * Call next() inside the transaction that uses the number: the row stays locked
 * until commit, so two tills never get the same number, and a rollback gives it back.
 */
class NumberSequence
{
    public static function next(string $name): int
    {
        DB::table('number_sequences')->insertOrIgnore(['name' => $name, 'value' => 0]);

        $current = (int) DB::table('number_sequences')->where('name', $name)->lockForUpdate()->value('value');

        DB::table('number_sequences')->where('name', $name)->update(['value' => $current + 1]);

        return $current + 1;
    }
}
