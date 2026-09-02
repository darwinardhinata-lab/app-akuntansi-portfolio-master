<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Indikator Include/Exclude PPN
            $table->boolean('is_include_ppn')->default(false);
            
            // Relasi ke Master Pajak
            $table->foreignId('tax_addition_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->foreignId('tax_deduction_id')->nullable()->constrained('taxes')->nullOnDelete();
            
            // Menyimpan nominal pajaknya (agar aman jika rate master pajak berubah di masa depan)
            $table->decimal('tax_addition_amount', 15, 2)->default(0);
            $table->decimal('tax_deduction_amount', 15, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['tax_addition_id']);
            $table->dropForeign(['tax_deduction_id']);
            $table->dropColumn([
                'is_include_ppn', 'tax_addition_id', 'tax_deduction_id', 
                'tax_addition_amount', 'tax_deduction_amount'
            ]);
        });
    }
};