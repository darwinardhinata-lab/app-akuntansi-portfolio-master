<?php

return [
    /*
     * Katalog awal pajak Indonesia untuk Master Pajak.
     * Tarif PPh dapat bergantung pada jenis transaksi, status lawan transaksi,
     * dan ketentuan khusus. Pastikan konfigurasi ini ditinjau sebelum dipakai
     * dalam perhitungan atau pelaporan pajak perusahaan.
     */
    'indonesia_defaults' => [
        [
            'tax_code' => 'PPN-11',
            'tax_name' => 'PPN',
            'rate' => 11.00,
            'tax_type' => 'ADDITION',
            'description' => 'Pajak Pertambahan Nilai (tarif efektif umum).',
        ],
        [
            'tax_code' => 'PPH-21',
            'tax_name' => 'PPh Pasal 21',
            'rate' => 5.00,
            'tax_type' => 'DEDUCTION',
            'description' => 'Pajak penghasilan atas orang pribadi; tarif aktual mengikuti lapisan dan status subjek pajak.',
        ],
        [
            'tax_code' => 'PPH-22',
            'tax_name' => 'PPh Pasal 22',
            'rate' => 1.50,
            'tax_type' => 'DEDUCTION',
            'description' => 'Pemungutan PPh Pasal 22 untuk transaksi yang memenuhi ketentuan.',
        ],
        [
            'tax_code' => 'PPH-23-JASA',
            'tax_name' => 'PPh Pasal 23 - Jasa',
            'rate' => 2.00,
            'tax_type' => 'DEDUCTION',
            'description' => 'Pemotongan PPh Pasal 23 atas jasa sesuai ketentuan yang berlaku.',
        ],
        [
            'tax_code' => 'PPH-23-SEWA',
            'tax_name' => 'PPh Pasal 23 - Sewa Selain Tanah/Bangunan',
            'rate' => 2.00,
            'tax_type' => 'DEDUCTION',
            'description' => 'Pemotongan PPh Pasal 23 atas sewa selain tanah dan/atau bangunan.',
        ],
        [
            'tax_code' => 'PPH-4-2-SEWA',
            'tax_name' => 'PPh Final Pasal 4 Ayat 2 - Sewa Tanah/Bangunan',
            'rate' => 10.00,
            'tax_type' => 'DEDUCTION',
            'description' => 'PPh final atas persewaan tanah dan/atau bangunan.',
        ],
        [
            'tax_code' => 'PPH-4-2-UMKM',
            'tax_name' => 'PPh Final UMKM',
            'rate' => 0.50,
            'tax_type' => 'DEDUCTION',
            'description' => 'PPh final UMKM bagi wajib pajak yang memenuhi persyaratan ketentuan.',
        ],
    ],
];
