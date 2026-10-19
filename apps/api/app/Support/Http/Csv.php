<?php

namespace App\Support\Http;

/** Helpers for CSV exports that staff open in Excel or Google Sheets. */
final class Csv
{
    /**
     * Neutralise spreadsheet formulas: text starting with = + - @ (or a tab/CR) is prefixed
     * with an apostrophe so a course title like "=HYPERLINK(...)" stays text. Real numbers,
     * including negative amounts, pass through unchanged.
     */
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
