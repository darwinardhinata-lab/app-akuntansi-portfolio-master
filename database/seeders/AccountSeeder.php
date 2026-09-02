<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            // KELOMPOK ASSET (HARTA)
            ['account_code' => '110101', 'account_name' => 'Kas di Tangan', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA'],
            ['account_code' => '110102', 'account_name' => 'Bank BCA', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA'],
            ['account_code' => '110201', 'account_name' => 'Piutang Usaha', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA'],
            
            // KELOMPOK LIABILITY (HUTANG)
            ['account_code' => '210101', 'account_name' => 'Hutang Usaha', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA'],
            
            // KELOMPOK EQUITY (MODAL)
            ['account_code' => '310101', 'account_name' => 'Modal Pemilik', 'coa_type' => 'EQUITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA'],
            
            // KELOMPOK PENDAPATAN
            ['account_code' => '410101', 'account_name' => 'Pendapatan Jasa', 'coa_type' => 'PENDAPATAN', 'normal_balance' => 'KREDIT', 'report_pos' => 'LABA RUGI'],
            
            // KELOMPOK BIAYA
            ['account_code' => '510101', 'account_name' => 'Biaya Gaji Karyawan', 'coa_type' => 'BIAYA', 'normal_balance' => 'DEBET', 'report_pos' => 'LABA RUGI'],
            ['account_code' => '510102', 'account_name' => 'Biaya Listrik & Air', 'coa_type' => 'BIAYA', 'normal_balance' => 'DEBET', 'report_pos' => 'LABA RUGI'],
        ];

        foreach ($akun as $data) {
            Account::create($data);
        }
    }
}