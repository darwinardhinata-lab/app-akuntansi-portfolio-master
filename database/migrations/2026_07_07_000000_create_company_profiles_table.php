<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('npwp')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Insert Data Awal sesuai Pemetaan Anda
        DB::table('company_profiles')->insert([
            'company_name' => 'BBW',
            'npwp' => '-',
            'website' => '-',
            'address' => 'Jl sumbing utara no8',
            'province' => 'Jawa Tengah',
            'city' => 'Surakarta',
            'country' => 'Indonesia',
            'postal_code' => '57139',
            'phone' => '08113113234',
            'email' => '-',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};