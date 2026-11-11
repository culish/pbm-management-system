<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran');
            $table->string('term');
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['tahun_ajaran', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('program_studi');
    }
};
