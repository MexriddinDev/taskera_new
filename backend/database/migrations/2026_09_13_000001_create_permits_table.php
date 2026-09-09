<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elektron ruxsatnoma — binoga kiruvchi tashrifchilar qaydi.
 *
 * Zayavka tizimidan ATAYLAB ajratilgan: bu ish oqimi emas, oddiy qayd
 * daftari. Tasdiqlash bosqichi, SLA, biriktirish va yozishma yo'q.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->string('full_name', 255);
            // PASSPORT | DRIVER_LICENSE — ro'yxat qisqa va o'zgarmas, shuning
            // uchun alohida ma'lumotnoma jadvali ochilmadi.
            $table->string('document_type', 32);
            $table->string('document_number', 64)->nullable();
            $table->text('visit_purpose');
            $table->timestampTz('visit_at')->nullable();
            $table->string('visitor_organization', 255)->nullable();
            $table->string('host_department', 255)->nullable();

            $table->timestampsTz();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();

            $table->index(['organization_id', 'visit_at']);
            $table->index(['organization_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permits');
    }
};
