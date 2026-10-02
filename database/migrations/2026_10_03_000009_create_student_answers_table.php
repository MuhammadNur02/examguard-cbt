<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('options')->nullOnDelete();
            $table->text('teks_jawaban')->nullable();
            $table->boolean('ragu')->default(false);
            $table->dateTime('disimpan_pada')->nullable();
            // Esai: kemiripan kosinus 0–1 terhadap kunci.
            $table->decimal('similarity', 5, 4)->nullable();
            // PG: bobot bila benar, 0 bila salah. Esai: similarity × bobot (rekomendasi).
            $table->decimal('skor_sistem', 8, 2)->nullable();
            // Nilai yang berlaku. Esai: keputusan dosen.
            $table->decimal('skor_final', 8, 2)->nullable();
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dinilai_pada')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
