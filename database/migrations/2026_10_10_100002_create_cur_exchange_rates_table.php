<?php

// FIX: multicurrency — snapshot kurs terpisah dengan rentang berlaku KMK dan audit aktor.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cur_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('cur_currencies')->restrictOnDelete();
            $table->decimal('rate', 20, 8);
            $table->date('rate_date');
            $table->date('valid_until')->nullable();
            $table->enum('type', ['SPOT', 'TAX', 'AVERAGE'])->default('SPOT');
            $table->string('source', 50)->default('MANUAL');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'currency_code', 'rate_date', 'type'], 'cur_rate_scope_unique');
            $table->index(['currency_code', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cur_exchange_rates');
    }
};