<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elektron ruxsatnoma so'rovlari.
 *
 * Xodim tashrifchi uchun ruxsat so'raydi, Ichki xavfsizlik uni tasdiqlaydi yoki
 * rad etadi. Mavjud `permits` jadvalidan ALOHIDA: u tasdiqlangan tashrifchilar
 * qaydi, bu esa qaror kutayotgan so'rovlar navbati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            // Kim so'radi.
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();

            // Kim uchun so'ralyapti.
            $table->string('visitor_name', 255);
            $table->string('visitor_organization', 255)->nullable();
            $table->string('document_number', 64)->nullable();
            $table->text('visit_purpose');
            $table->timestampTz('visit_at')->nullable();

            // PENDING | APPROVED | REJECTED — ro'yxat qisqa va o'zgarmas,
            // shuning uchun alohida ma'lumotnoma jadvali ochilmadi.
            $table->string('status', 16)->default('PENDING');
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Ichki xavfsizlik navbatni holat bo'yicha ochadi, xodim esa o'zinikini.
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'requester_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_requests');
    }
};
