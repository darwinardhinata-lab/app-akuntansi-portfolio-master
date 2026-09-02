<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DocumentSequence
{
    public static function generateSecure(string $table, string $column, string $prefix, int $padLength = 4): string
    {
        $lastRecord = DB::table($table)
            ->where($column, 'like', $prefix . '%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->first();

        $seq = 1;
        if ($lastRecord) {
            $seq = (int) substr($lastRecord->$column, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $seq, $padLength, '0', STR_PAD_LEFT);
    }

    public static function preview(string $table, string $column, string $prefix, int $padLength = 4): string
    {
        $lastRecord = DB::table($table)
            ->where($column, 'like', $prefix . '%')
            ->orderByDesc($column)
            ->first();

        $seq = 1;
        if ($lastRecord) {
            $seq = (int) substr($lastRecord->$column, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $seq, $padLength, '0', STR_PAD_LEFT);
    }
}