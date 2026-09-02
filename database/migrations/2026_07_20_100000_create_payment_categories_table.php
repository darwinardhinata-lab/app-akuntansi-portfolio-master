<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default categories
        DB::table('payment_categories')->insert([
            ['name' => 'PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)', 'slug' => 'hutang', 'description' => 'Pembayaran hutang persediaan barang', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PEMBELIAN PERSEDIAAN (UANG MUKA)', 'slug' => 'uang-muka', 'description' => 'Pembayaran uang muka persediaan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PEMBELIAN & OPERASIONAL', 'slug' => 'operasional', 'description' => 'Pembayaran operasional', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ASET', 'slug' => 'aset', 'description' => 'Pembayaran aset', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PRIVE', 'slug' => 'prive', 'description' => 'Pengembalian prive', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PB', 'slug' => 'pb', 'description' => 'Penerimaan kas bon', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('payment_categories');
    }
};