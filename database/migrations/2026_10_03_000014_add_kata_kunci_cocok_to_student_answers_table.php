<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            // Checklist kata kunci dari layanan NLP (K-3, FR-05.4): daftar kata kunci yang terpenuhi.
            $table->json('kata_kunci_cocok')->nullable()->after('similarity');
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn('kata_kunci_cocok');
        });
    }
};
