<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->string('jenis', 30);
            // Waktu diterima server, presisi milidetik.
            $table->dateTime('waktu', precision: 3);
            $table->json('detail')->nullable();
            // false untuk insiden yang hanya dicatat (mis. perangkat berganti).
            $table->boolean('dihitung')->default(true);
            $table->boolean('dimaafkan')->default(false);
            $table->foreignId('dimaafkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dimaafkan_pada')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamps();
            $table->index(['attempt_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_logs');
    }
};
