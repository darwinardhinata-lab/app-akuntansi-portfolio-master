<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DocumentSequence
{
    public static function reserve(string $table, string $column, string $prefix, int $padLength = 4): string
    {
        return DB::transaction(function () use ($table, $column, $prefix, $padLength) {
            $key = hash('sha256', $table.'|'.$column.'|'.$prefix);
            DB::table('document_sequence_counters')->insertOrIgnore(['sequence_key' => $key, 'last_number' => 0]);
            $counter = DB::table('document_sequence_counters')->where('sequence_key', $key)->lockForUpdate()->first();
            $max = (int) $counter->last_number;
            // Seed/reconcile with existing externally imported numbers, including padding overflow.
            foreach (DB::table($table)->where($column, 'like', $prefix.'%')->pluck($column) as $number) {
                $suffix = substr($number, strlen($prefix));
                if (str_starts_with($number, $prefix) && ctype_digit($suffix)) $max = max($max, (int) $suffix);
            }
            $next = $max + 1;
            DB::table('document_sequence_counters')->where('sequence_key', $key)->update(['last_number' => $next]);
            return $prefix.str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
        }, 3);
    }

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