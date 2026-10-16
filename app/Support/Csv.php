<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download (UTF-8 with BOM so Excel shows non-ASCII text correctly).
 */
class Csv
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas in text cells (CSV injection).
                fputcsv($out, array_map(fn ($value) => is_string($value) && ! is_numeric($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
