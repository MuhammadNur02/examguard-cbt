<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            // Nomor asli menurut dosen (sebelum diacak).
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('tipe', 10);
            $table->text('teks');
            $table->decimal('bobot', 6, 2)->default(1);
            $table->text('kunci_esai')->nullable();
            $table->json('keywords')->nullable();
            $table->timestamps();
            $table->index(['exam_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
