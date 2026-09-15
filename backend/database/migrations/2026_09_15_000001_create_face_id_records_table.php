<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ichki xavfsizlik → FaceID yozuvlari.
 *
 * Ism/familiya PINFL bo'yicha HR API'dan keladi, tug'ilgan sana PINFL
 * raqamining o'zidan hisoblanadi — lekin ikkalasi ham jadvalda SAQLANADI:
 * tashqi API keyin javob bermasligi mumkin, yozuv esa o'zgarmas qolishi kerak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_id_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->string('pinfl', 14);
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->string('middle_name', 128)->nullable();
            $table->date('birth_date')->nullable();

            // Yuz rasmi va biriktirilgan PDF hujjat — disk yo'llari.
            $table->string('photo_path', 512)->nullable();
            $table->string('document_path', 512)->nullable();

            $table->timestampsTz();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();

            // Ro'yxat tashkilot bo'yicha filtrlanadi va PINFL bo'yicha qidiriladi.
            $table->index(['organization_id', 'pinfl']);
            $table->index(['organization_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_id_records');
    }
};
