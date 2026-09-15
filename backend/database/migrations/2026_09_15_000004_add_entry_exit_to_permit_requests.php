<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tashrifchining kirish va chiqish vaqti.
 *
 * Qorovul postida ruxsat berilgani yetarli emas: mijoz haqiqatan kirdimi va
 * qachon chiqdi — shu ikki payt qayd etiladi. Tugmalarni Ichki xavfsizlik
 * bosadi, shuning uchun kim bosgani ham saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->timestampTz('entered_at')->nullable()->after('decided_at');
            $table->foreignId('entered_by')->nullable()->after('entered_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('exited_at')->nullable()->after('entered_by');
            $table->foreignId('exited_by')->nullable()->after('exited_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('entered_by');
            $table->dropConstrainedForeignKey('exited_by');
            $table->dropColumn(['entered_at', 'exited_at']);
        });
    }
};
