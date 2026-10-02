<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->unique()->constrained('exams')->cascadeOnDelete();
            $table->string('kode_akses', 50)->nullable();
            // Daftar CIDR yang diizinkan, satu per baris (FR-02.9).
            $table->text('ip_allowlist')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_access');
    }
};
