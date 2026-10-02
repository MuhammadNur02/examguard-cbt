<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained('exam_attempts')->cascadeOnDelete();
            $table->decimal('skor_pg', 8, 2)->default(0);
            $table->decimal('skor_esai_sistem', 8, 2)->nullable();
            $table->decimal('skor_esai_final', 8, 2)->nullable();
            // Jumlah bobot soal yang dikerjakan pada attempt.
            $table->decimal('skor_maksimal', 8, 2)->default(0);
            // Skala 0–100; terisi setelah semua esai dikonfirmasi dosen.
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->dateTime('dipublikasikan_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
