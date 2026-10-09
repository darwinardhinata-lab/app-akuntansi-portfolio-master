<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('maker_user_id')->nullable();
            $table->unsignedBigInteger('last_editor_user_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_payment_plan', function (Blueprint $table) {
            $table->dropColumn(['maker_user_id', 'last_editor_user_id', 'approver_user_id', 'approved_at']);
        });
    }
};