<?php
// database/migrations/2026_08_10_000001_restructure_payment_plan_to_header_detail.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom yang dipakai controller tapi belum ada di tabel lama
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_payment_plan', 'ref_po_number')) {
                $table->string('ref_po_number', 50)->nullable()->after('journal_id');
            }
            if (!Schema::hasColumn('transaksi_payment_plan', 'ref_bill_number')) {
                $table->string('ref_bill_number', 50)->nullable()->after('ref_po_number');
            }
        });

        // 2. Buat tabel detail baru
        Schema::create('transaksi_payment_plan_detail', function (Blueprint $table) {
            $table->id('id_detail');
            $table->unsignedBigInteger('id_payment');
            $table->string('nama_item', 255)->nullable();
            $table->decimal('qty', 10, 2)->default(1.00);
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->string('satuan', 20)->default('Pcs');
            $table->text('keterangan')->nullable();
            $table->string('bukti_file', 255)->nullable();
            $table->decimal('nominal', 15, 2)->default(0);
            $table->decimal('nominal_aktual', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_payment')
                  ->references('id_payment')->on('transaksi_payment_plan')
                  ->onDelete('cascade');
            $table->index('id_payment');
        });

        // 3. Backfill: setiap row lama di parent jadi 1 baris detail
        //    (supaya data existing tidak hilang saat pindah ke struktur header-detail)
        DB::table('transaksi_payment_plan')->orderBy('id_payment')->chunk(200, function ($rows) {
            $now = now();
            $insert = [];
            foreach ($rows as $row) {
                $insert[] = [
                    'id_payment'     => $row->id_payment,
                    'nama_item'      => null,
                    'qty'            => $row->qty ?? 1,
                    'harga_satuan'   => $row->harga_satuan,
                    'satuan'         => $row->satuan ?? 'Pcs',
                    'keterangan'     => $row->keterangan,
                    'bukti_file'     => $row->bukti_file,
                    'nominal'        => $row->nominal,
                    'nominal_aktual' => $row->nominal_aktual,
                    'created_at'     => $row->created_at ?? $now,
                    'updated_at'     => $now,
                ];
            }
            if (!empty($insert)) {
                DB::table('transaksi_payment_plan_detail')->insert($insert);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_payment_plan_detail');

        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_payment_plan', 'ref_bill_number')) {
                $table->dropColumn('ref_bill_number');
            }
            if (Schema::hasColumn('transaksi_payment_plan', 'ref_po_number')) {
                $table->dropColumn('ref_po_number');
            }
        });
    }
};