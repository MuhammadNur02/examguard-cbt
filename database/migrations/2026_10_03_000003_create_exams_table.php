<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('users')->restrictOnDelete();
            $table->string('judul');
            $table->string('mata_kuliah');
            // Jendela ujian: [mulai, mulai + durasi_menit].
            $table->dateTime('mulai');
            $table->unsignedSmallInteger('durasi_menit');
            // N: pelanggaran ke-(N+1) memicu auto-submit (PRD K-1).
            $table->unsignedTinyInteger('batas_pelanggaran')->default(3);
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            // Pool soal N dari M (FR-03.6); null = semua soal.
            $table->unsignedSmallInteger('pool_size')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
