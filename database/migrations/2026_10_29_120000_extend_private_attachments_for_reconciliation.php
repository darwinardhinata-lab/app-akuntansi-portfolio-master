<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppl_attachments', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->change();
            $table->foreignId('reconciliation_id')->nullable()->constrained('ppl_ap_reconciliations')->restrictOnDelete();
            $table->unique(['reconciliation_id', 'sha256']);
        });
        Schema::create('ppl_attachment_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('attachment_id');
            $table->foreign('attachment_id')->references('id')->on('ppl_attachments')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->timestamp('created_at');
        });
    }

    public function down(): void { throw new RuntimeException('Private attachment evidence and audit must be retained.'); }
};