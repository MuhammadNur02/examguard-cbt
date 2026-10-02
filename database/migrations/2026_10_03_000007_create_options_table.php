<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            // Label asli A–E menurut dosen.
            $table->string('label', 1);
            $table->text('teks');
            $table->boolean('is_correct')->default(false);
            // Opsi tidak ikut diacak, mis. "Semua benar" (FR-03.5).
            $table->boolean('posisi_tetap')->default(false);
            $table->timestamps();
            $table->unique(['question_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
