<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('company_profiles')->whereNull('employee_pin')->update([
            'employee_pin' => '123456',
        ]);
    }
}