<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cisco Finesse (UCCX) hisob ma'lumotlari — har foydalanuvchi uchun bitta.
 *
 * DIQQAT: parol HASH EMAS, qaytariladigan shifrda saqlanadi. Finesse HTTP
 * Basic auth ishlatadi, ya'ni parolni serverga qayta yuborish kerak —
 * hashdan uni tiklab bo'lmaydi. Shifrlash `Crypt::encryptString()` orqali,
 * loyihadagi AD akkauntlari bilan bir xil naqsh (users.password_encrypted).
 *
 * Extension va holat API'dan keladi va shu yerda keshlanadi — sahifa
 * ochilishida darhol ko'rsatish uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finesse_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            // Bitta foydalanuvchi — bitta Finesse hisobi.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('login_id', 128);
            $table->text('password_encrypted');

            // Finesse javobidan keshlanadi (so'ralmaydi, avtomatik to'ladi).
            $table->string('extension', 32)->nullable();
            $table->string('last_state', 32)->nullable();
            $table->timestampTz('last_checked_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finesse_accounts');
    }
};
