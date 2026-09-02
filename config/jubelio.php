<?php

return [
    // Peta nama "Rekening Ops" internal ERP -> nama Akun Kas/Bank yang TERDAFTAR di Jubelio.
    // WAJIB dicocokkan oleh Admin/Finance dengan master akun kas/bank yang sudah ada di Jubelio,
    // supaya nilai kolom "Akun Kas/Bank" saat import tidak ditolak oleh Jubelio.
    'rekening_map' => [
        'BCA BBW OPS' => 'Bank BCA BBW OPS',
        'BCA BBB OPS' => 'Bank BCA BBB OPS',
        'BCA KOI OPS' => 'Bank BCA KOI OPS',
        'BCA GBB OPS' => 'Bank BCA GBB OPS',
        'BCA BBW'     => 'Bank BCA BBW',
        'BCA BBB'     => 'Bank BCA BBB',
        'BCA KOI'     => 'Bank BCA KOI',
        'BCA GBB'     => 'Bank BCA GBB',
        'MANDIRI BBW' => 'Bank Mandiri BBW',
        'MANDIRI KOI' => 'Bank Mandiri KOI',
        'MANDIRI BBB' => 'Bank Mandiri BBB',
        'XENDIT'      => 'Xendit',
        'BRI BBW'     => 'Bank BRI BBW',
    ],

    // Nilai kolom "No" pada export Kas & Bank Jubelio.
    // '[auto]' = Jubelio generate nomor sendiri (disarankan, menghindari bentrok nomor).
    'no_transaksi_default' => '[auto]',
];