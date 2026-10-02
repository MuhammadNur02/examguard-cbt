<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penetapan ujian ke kelas (FR-02.6).
        Schema::create('exam_classes', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->primary(['exam_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_classes');
    }
};
