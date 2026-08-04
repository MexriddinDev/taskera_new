<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Xodim kartochkasi bo'lmasa ham (AD sinxronizatsiyasi kelguncha) Telegram
        // akkauntini user bilan bog'lash mumkin bo'lishi uchun employee_id nullable.
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
        });

        // Bot suhbat holati (state machine) — foydalanuvchi qaysi qadamda ekanini saqlaydi
        Schema::create('telegram_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->string('chat_id', 32);
            $table->string('telegram_user_id', 32);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('state', 64)->default('IDLE');
            $table->jsonb('data')->nullable();
            $table->timestampTz('last_activity_at');
            $table->timestampsTz();

            $table->unique(['bot_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_sessions');

        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
