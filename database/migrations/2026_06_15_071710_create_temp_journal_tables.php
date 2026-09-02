<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ==========================================
        // 1. TABEL HEADER TEMP (jh_temp)
        // ==========================================
        Schema::create('jh_temp', function (Blueprint $table) {
            $table->bigInteger('journal_id')->primary();
            $table->date('journal_date')->nullable()->index();
            $table->string('journal_code', 100)->nullable()->index();
            $table->string('source_doc_no', 100)->nullable()->index();
            $table->string('invoice_id', 50)->nullable();
            $table->string('transaction_type', 100)->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->text('journal_description')->nullable();
            $table->string('sync_status', 20)->default('PENDING')->index();
            $table->timestamps();
        });

        // ==========================================
        // 2. TABEL DETAIL TEMP (jd_temp)
        // ==========================================
        Schema::create('jd_temp', function (Blueprint $table) {
            $table->bigInteger('journal_detail_id')->primary();
            $table->bigInteger('journal_id')->index();
            $table->bigInteger('coa_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->string('tag_id', 50)->nullable();
            $table->string('tag_name', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jd_temp');
        Schema::dropIfExists('jh_temp');
    }
};