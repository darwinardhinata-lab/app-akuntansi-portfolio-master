<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bagian 2 & 6: "Satukan party dan party_role; kode bantu dipertahankan
     * sebagai referensi." Tabel ini TIDAK menggantikan helper_codes (yang
     * tetap dipakai jurnal_details.helper_code) maupun mfg_suppliers -
     * keduanya masih berjalan seperti biasa. parties adalah master baru
     * untuk customer/supplier/dst ke depan; penautan dokumen transaksi
     * (sales_orders.customer_name, purchase_orders.supplier_name, dst) ke
     * parties adalah pekerjaan Tahap 2, sengaja belum dilakukan di sini.
     */
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('legal_name', 255);
            $table->string('tax_no', 50)->nullable(); // NPWP
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
