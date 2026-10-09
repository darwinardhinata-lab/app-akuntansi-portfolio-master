<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapping Chart of Accounts (COA) Sistem
    |--------------------------------------------------------------------------
    */
    'piutang_usaha'    => env('COA_PIUTANG', '113101'),
    'persediaan'       => env('COA_PERSEDIAAN', '114001'),
    'uang_muka_beli'   => env('COA_DP_PEMBELIAN', '116002'),
    'uang_muka_jual'   => env('COA_DP_PENJUALAN', '231001'),
    'hutang_usaha'     => env('COA_HUTANG', '211001'),

    // Sales type / exceptional discounts require an approved explicit mapping.
    'penjualan'        => env('COA_PENJUALAN'),
    'diskon_penjualan' => env('COA_DISKON_JUAL', '411005'),
    'diskon_ongkir'    => env('COA_DISKON_ONGKIR'),
    'diskon_lain'      => env('COA_DISKON_LAIN'),
    'ongkos_kirim'     => env('COA_ONGKIR', '810007'),
    
    // Charged to customers as income; do not infer an expense account from the key.
    'biaya_lain'       => env('COA_BIAYA_LAIN'),

    'hpp'              => env('COA_HPP', '510001'),

    // PERBAIKAN: Disesuaikan dengan master COA aktual untuk mencegah jurnal nyasar
    'pajak_keluaran'   => env('COA_PAJAK_KELUARAN', '213108'),
    'pajak_masukan'    => env('COA_PAJAK_MASUKAN', '117008'),

    // Asset category mapping must be supplied; no generic asset/depreciation fallback.
    'aset_tetap'       => env('COA_ASET_TETAP'),
    'akum_penyusutan'  => env('COA_AKUM_PENYUSUTAN'),
    'beban_penyusutan' => env('COA_BEBAN_PENYUSUTAN'),
    
    // COA tambahan untuk retur dan operasional lain
    'biaya_kirim'      => env('COA_BIAYA_KIRIM'),
    'retur_penjualan'  => env('COA_RETUR_PENJUALAN', '411006'),
    'selisih_retur_pembelian' => env('COA_SELISIH_RETUR_PEMBELIAN'),
    'retur_shopee'     => env('COA_RETUR_SHOPEE'),
    'retur_tiktok'     => env('COA_RETUR_TIKTOK'),
    'kerugian_barang_cacat' => env('COA_KERUGIAN_BARANG_CACAT'),
    
    // Kunci 'aset_tetap' yang duplikat telah dihapus dari sini untuk clean code
    
    'pembulatan'       => env('COA_PEMBULATAN', '910008'),

    /*
    |--------------------------------------------------------------------------
    | Mapping COA — Modul Manufaktur (MFG/SPK)
    |--------------------------------------------------------------------------
    | Ditambahkan sebagai bagian dari integrasi Anthrilo Manufacturing.
    | Lihat MANUFACTURING_INTEGRATION.md untuk alur jurnal lengkap per tahap.
    */
    'persediaan_bahan_baku_benang' => env('COA_MFG_YARN', '114003'),
    'persediaan_bahan_baku_kain'   => env('COA_MFG_FABRIC'),
    'wip_produksi'                 => env('COA_MFG_WIP', '114002'),
    'persediaan_barang_jadi'       => env('COA_MFG_FG', '114001'),
    'hutang_usaha_maklun'          => env('COA_MFG_HUTANG_JASA'),
    'biaya_jasa_knitting'          => env('COA_MFG_KNITTING'),
    'biaya_jasa_proses_kain'       => env('COA_MFG_PROCESSING'),
    'biaya_jasa_jahit'             => env('COA_MFG_STITCHING'),
    'kerugian_wastage_produksi'    => env('COA_MFG_WASTAGE'),
    'selisih_produksi'             => env('COA_MFG_VARIANCE'),
];
