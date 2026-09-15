<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ruxsatnoma so'rovi to'liq ma'lumot bilan to'ldiriladi.
 *
 * Dastlab so'rovda faqat `visitor_name` bor edi, lekin qorovul postiga
 * kerakli ma'lumot ancha ko'p: F.I.Sh alohida maydonlarda, guvohnoma turi va
 * raqami, rasm va qaysi bo'limga kelayotgani. Bu maydonlar eski `permits`
 * (tashrifchilar qaydi) shaklidan olindi — endi hammasi bitta oqimda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->after('requester_user_id');
            $table->string('first_name', 100)->nullable()->after('last_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('document_type', 32)->nullable()->after('middle_name');
            $table->string('photo_path', 512)->nullable()->after('document_number');
            $table->string('host_department', 255)->nullable()->after('photo_path');
        });

        // Mavjud so'rovlar yo'qolmasin: to'liq ism familiya ustuniga ko'chadi.
        DB::table('permit_requests')
            ->whereNull('last_name')
            ->update(['last_name' => DB::raw('visitor_name')]);

        Schema::table('permit_requests', function (Blueprint $table) {
            $table->dropColumn('visitor_name');
        });
    }

    public function down(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->string('visitor_name', 255)->nullable();
        });

        DB::table('permit_requests')->update(['visitor_name' => DB::raw('last_name')]);

        Schema::table('permit_requests', function (Blueprint $table) {
            $table->dropColumn([
                'last_name', 'first_name', 'middle_name',
                'document_type', 'photo_path', 'host_department',
            ]);
        });
    }
};
