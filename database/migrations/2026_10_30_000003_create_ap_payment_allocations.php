<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppl_ap_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->uuid('payment_request_id')->constrained('ppl_requests')->restrictOnDelete();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->restrictOnDelete();
            $table->decimal('allocated_amount', 20, 2);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('allocated_at')->useCurrent();
            $table->string('journal_id', 50)->nullable();
            $table->string('idempotency_key', 150)->unique();
            $table->timestamps();
        });

        Schema::table('ppl_ap_payment_allocations', function (Blueprint $table) {
            $table->index(['company_id', 'purchase_bill_id']);
            $table->index(['payment_request_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('ppl_ap_payment_allocations')->exists()) {
            throw new \RuntimeException('AP payment allocation evidence must be retained.');
        }
        Schema::dropIfExists('ppl_ap_payment_allocations');
    }
};