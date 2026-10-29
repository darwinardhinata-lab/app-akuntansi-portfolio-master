<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // MySQL unique indexes permit multiple NULL values. The existing request/kind/hash
        // index remains effective for request attachments, the new index covers reconciliation
        // attachments, and this check prevents a row belonging to neither or both contexts.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ppl_attachments ADD CONSTRAINT ppl_attachments_one_context CHECK ((request_id IS NULL) <> (reconciliation_id IS NULL))');
        }
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