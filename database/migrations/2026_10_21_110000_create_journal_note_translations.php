<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_note_translations', function (Blueprint $table) {
            $table->id();
            $table->string('journal_id', 50);
            $table->string('locale', 10);
            $table->string('source_hash', 64);
            $table->text('translated_text');
            $table->timestamps();
            $table->unique(['journal_id', 'locale', 'source_hash'], 'journal_note_translation_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_note_translations');
    }
};