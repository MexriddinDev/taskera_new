<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruxsatnoma: F.I.Sh uchta alohida ustunga ajratiladi, tashkilot nomi
 * o'rniga tashrifchi rasmi (jpg/png) saqlanadi.
 *
 * Ustun nomlari `employees` jadvalidagi konvensiya bilan bir xil:
 * last_name (Familiya) + first_name (Ism) majburiy, middle_name (Sharif) ixtiyoriy.
 */
return new class extends Migration
{
    public function up(): void
    {
        // full_name indeksda qatnashadi — ustunni o'chirishdan oldin indeks olinadi.
        Schema::table('permits', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'full_name']);
        });

        Schema::table('permits', function (Blueprint $table) {
            $table->string('last_name', 100)->after('organization_id');
            $table->string('first_name', 100)->after('last_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            // Faqat rasm saqlanadi; disk `attachments` bilan bir xil ('public',
            // yozib bo'lmasa 'local' — PermitController shu tartibda urinadi).
            $table->string('photo_path', 255)->nullable()->after('visit_at');
        });

        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn(['full_name', 'visitor_organization']);
            $table->index(['organization_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'last_name']);
            $table->string('full_name', 255)->after('organization_id');
            $table->string('visitor_organization', 255)->nullable()->after('visit_at');
        });

        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'first_name', 'middle_name', 'photo_path']);
            $table->index(['organization_id', 'full_name']);
        });
    }
};
