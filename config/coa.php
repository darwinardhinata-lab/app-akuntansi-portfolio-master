<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapping Chart of Accounts (COA) Sistem
    |--------------------------------------------------------------------------
    */
    'piutang_usaha'    => env('COA_PIUTANG', '11100'),
    'persediaan'       => env('COA_PERSEDIAAN', '11200'),
    'uang_muka_beli'   => env('COA_DP_PEMBELIAN', '11305'),
    'hutang_usaha'     => env('COA_HUTANG', '22000'),

    'penjualan'        => env('COA_PENJUALAN', '44000'),
    'diskon_penjualan' => env('COA_DISKON_JUAL', '44001'),
    'diskon_ongkir'    => env('COA_DISKON_ONGKIR', '66499'),
    'diskon_lain'      => env('COA_DISKON_LAIN', '44002'),
    'ongkos_kirim'     => env('COA_ONGKIR', '77005'),
    
    // Catatan: Akun 44004 di master adalah "Pendapatan Lain-lain Shopee" (Normal: KREDIT).
    // Secara sistem digunakan untuk menampung pendapatan ekstra/tagihan lain ke pelanggan dari semua channel.
    'biaya_lain'       => env('COA_BIAYA_LAIN', '44004'),

    'hpp'              => env('COA_HPP', '55000'),

    // PERBAIKAN: Disesuaikan dengan master COA aktual untuk mencegah jurnal nyasar
    'pajak_keluaran'   => env('COA_PAJAK_KELUARAN', '22103'), // Sebelumnya 21104
    'pajak_masukan'    => env('COA_PAJAK_MASUKAN', '11303'),  // Sebelumnya 11401

    'aset_tetap'       => env('COA_ASET_TETAP', '12000'),
    'akum_penyusutan'  => env('COA_AKUM_PENYUSUTAN', '12001'),
    'beban_penyusutan' => env('COA_BEBAN_PENYUSUTAN', '88002'),
    
    // COA tambahan untuk retur dan operasional lain
    'biaya_kirim'      => env('COA_BIAYA_KIRIM', '66281'),
    'retur_penjualan'  => env('COA_RETUR_PENJUALAN', '44010'),
    'retur_shopee'     => env('COA_RETUR_SHOPEE', '44006'),
    'retur_tiktok'     => env('COA_RETUR_TIKTOK', '44008'),
    'kerugian_barang_cacat' => env('COA_KERUGIAN_BARANG_CACAT', '88004'),
    
    // Kunci 'aset_tetap' yang duplikat telah dihapus dari sini untuk clean code
    
    'pembulatan'       => env('COA_PEMBULATAN', '88068'),
];