<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->string('exam_type', 3);
            $table->unsignedInteger('version');
            $table->foreignId('lecturer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('original_document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('signed_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('authorization_id')->nullable()->constrained('signature_authorizations')->restrictOnDelete();
            $table->string('status');
            $table->string('status_reason')->nullable();
            $table->string('placement')->nullable();
            $table->string('verification_code', 16)->nullable()->unique();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['mata_kuliah_id', 'semester_id', 'exam_type', 'version']);
            $table->index(['status', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_submissions');
    }
};
