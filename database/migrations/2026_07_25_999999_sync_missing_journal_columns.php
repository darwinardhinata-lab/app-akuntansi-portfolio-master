<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Sync journal_headers
        if (!Schema::hasColumn('journal_headers', 'evidence_number')) {
            Schema::table('journal_headers', function (Blueprint $table) {
                $table->string('evidence_number', 150)->nullable()->after('transaction_date');
            });
            
            // Only create index if it doesn't already exist
            if (!Schema::hasIndex('journal_headers', 'journal_headers_evidence_number_index')) {
                Schema::table('journal_headers', function (Blueprint $table) {
                    $table->index('evidence_number');
                });
            }
        }
        
        if (!Schema::hasColumn('journal_headers', 'jj_id')) {
            Schema::table('journal_headers', function (Blueprint $table) {
                $table->bigInteger('jj_id')->nullable()->after('description');
            });
        }

        // 2. Sync journal_details
        // Note: journal_details already has journal_detail_id as primary key, so we skip adding 'id'
        
        if (!Schema::hasColumn('journal_details', 'position')) {
            Schema::table('journal_details', function (Blueprint $table) {
                $table->enum('position', ['DEBET', 'KREDIT'])->nullable()->after('helper_code');
            });
        }
        
        if (!Schema::hasColumn('journal_details', 'amount')) {
            Schema::table('journal_details', function (Blueprint $table) {
                $table->decimal('amount', 20, 2)->default(0)->after('position');
            });
        }
    }

    public function down()
    {
        // Kosongkan down() agar rollback aman dan tidak membuang data krusial di production
    }
};