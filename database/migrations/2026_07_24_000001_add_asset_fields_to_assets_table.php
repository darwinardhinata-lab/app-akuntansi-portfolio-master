<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('category')->nullable()->after('asset_name');
            $table->integer('quantity')->default(1)->after('category');
            $table->boolean('is_active')->default(true)->after('quantity');
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['category', 'quantity', 'is_active']);
        });
    }
};