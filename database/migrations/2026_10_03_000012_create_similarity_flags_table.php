<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pasangan esai mirip antarmahasiswa pada satu soal (FR-05.5).
        Schema::create('similarity_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('attempt_a')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('attempt_b')->constrained('exam_attempts')->cascadeOnDelete();
            $table->decimal('skor', 5, 4);
            $table->timestamps();
            $table->unique(['question_id', 'attempt_a', 'attempt_b']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similarity_flags');
    }
};
