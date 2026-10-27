<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppl_ap_allocations', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->foreign('request_id')->references('id')->on('ppl_requests')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->string('status', 20);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        if (DB::table('ppl_ap_allocations')->exists()) throw new RuntimeException('AP allocation evidence must be retained.');
        Schema::dropIfExists('ppl_ap_allocations');
    }
};