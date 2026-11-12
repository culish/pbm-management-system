<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('program_studi_id')->constrained('program_studi')->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_nip')->nullable();
            $table->string('signer_title');
            $table->jsonb('exam_types');
            $table->string('image_path');
            $table->string('image_sha256', 64);
            $table->text('consent_statement');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->string('status')->default('aktif');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason')->nullable();
            $table->string('granted_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['program_studi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_authorizations');
    }
};
