<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->after('role_id')
                ->constrained('program_studi')->nullOnDelete();
        });

        Schema::create('mata_kuliah_pengampu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mata_kuliah_id', 'semester_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_kuliah_pengampu');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_studi_id');
        });
    }
};
