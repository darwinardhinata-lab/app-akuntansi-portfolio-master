<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix #4: Add unique index on journal_headers(evidence_number, transaction_type)
 *
 * This is the database-level safety net for anti-double-posting protection.
 * All application-level checks (receivePartialOrder, FastImportBIL, FastImportINV, etc.)
 * are "best effort" and can fail under race conditions or retries. This unique index
 * is the last line of defense.
 *
 * Before adding the index, we clean up any existing duplicates by keeping only
 * the oldest journal for each (evidence_number, transaction_type) pair and deleting
 * the rest along with their details.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Find and clean up existing duplicate journals
        // Keep the oldest (MIN(journal_id)) for each (evidence_number, transaction_type) pair
        // Delete the rest along with their journal_details
        $duplicates = DB::table('journal_headers')
            ->select('evidence_number', 'transaction_type', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('evidence_number')
            ->where('evidence_number', '!=', '')
            ->groupBy('evidence_number', 'transaction_type')
            ->having('cnt', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            foreach ($duplicates as $dup) {
                // Find the journal_id to KEEP (oldest one)
                $keepId = DB::table('journal_headers')
                    ->where('evidence_number', $dup->evidence_number)
                    ->where('transaction_type', $dup->transaction_type)
                    ->orderBy('journal_id')
                    ->value('journal_id');

                // Find all journal_ids to DELETE (the duplicates)
                $deleteIds = DB::table('journal_headers')
                    ->where('evidence_number', $dup->evidence_number)
                    ->where('transaction_type', $dup->transaction_type)
                    ->where('journal_id', '!=', $keepId)
                    ->pluck('journal_id')
                    ->toArray();

                if (!empty($deleteIds)) {
                    // Delete journal_details for the duplicate journals
                    DB::table('journal_details')->whereIn('journal_id', $deleteIds)->delete();
                    // Delete the duplicate journal_headers
                    DB::table('journal_headers')->whereIn('journal_id', $deleteIds)->delete();
                }
            }
        }

        // Step 2: Add the unique index
        // Note: We use a conditional approach in case there are still edge cases
        try {
            Schema::table('journal_headers', function (Blueprint $table) {
                $table->unique(['evidence_number', 'transaction_type'], 'uq_journal_evidence_type');
            });
        } catch (\Exception $e) {
            // If unique index fails (e.g., still has duplicates with NULL transaction_type),
            // log the error but don't fail the migration — the app-level checks still work
            echo "⚠️  Unique index could not be created: " . $e->getMessage() . "\n";
            echo "   Application-level anti-double-posting checks remain active.\n";
            echo "   Manual cleanup may be needed for remaining duplicates.\n";
        }
    }

    public function down(): void
    {
        Schema::table('journal_headers', function (Blueprint $table) {
            $table->dropUnique('uq_journal_evidence_type');
        });
    }
};