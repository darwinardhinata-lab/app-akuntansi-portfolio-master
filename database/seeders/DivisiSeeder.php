<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisiSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode_divisi' => 'HR', 'nama_divisi' => 'HR'],
            ['kode_divisi' => 'GA', 'nama_divisi' => 'GA'],
            ['kode_divisi' => 'FIN', 'nama_divisi' => 'FINANCE'],
            ['kode_divisi' => 'GDG', 'nama_divisi' => 'GUDANG'],
            ['kode_divisi' => 'DSN', 'nama_divisi' => 'DESAIN & KREATIF'],
            ['kode_divisi' => 'MP', 'nama_divisi' => 'MP'],
            ['kode_divisi' => 'LIV', 'nama_divisi' => 'LIVE'],
            ['kode_divisi' => 'PKL', 'nama_divisi' => 'TOKO PAKEL'],
            ['kode_divisi' => 'MJS', 'nama_divisi' => 'TOKO MOJOSONGO'],
            ['kode_divisi' => 'PJG', 'nama_divisi' => 'TOKO PAJANG'],
            ['kode_divisi' => 'BTP', 'nama_divisi' => 'TOKO BANGUNTAPAN'],
            ['kode_divisi' => 'CS', 'nama_divisi' => 'CS'],
            ['kode_divisi' => 'IT', 'nama_divisi' => 'IT'],
        ];

        foreach ($data as $d) {
            DB::table('master_divisi')->insert([
                'kode_divisi' => $d['kode_divisi'],
                'nama_divisi' => $d['nama_divisi'],
                'status_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}