<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Seed PRNG Fisher-Yates milik attempt ini (FR-03.3).
            $table->unsignedInteger('shuffle_seed');
            // Pemetaan urutan tampil ke ID asli (PRD §9.2).
            $table->json('urutan_soal');
            $table->json('urutan_opsi');
            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->string('status', 20)->default('berlangsung')->index();
            $table->string('alasan_selesai', 30)->nullable();
            // Penghitung pelanggaran sisi server (tidak termasuk yang dimaafkan).
            $table->unsignedSmallInteger('jumlah_pelanggaran')->default(0);
            $table->unsignedSmallInteger('waktu_tambahan')->default(0);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('terakhir_aktif')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
