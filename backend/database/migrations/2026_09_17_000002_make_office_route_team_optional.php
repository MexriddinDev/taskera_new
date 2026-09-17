<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ofis marshrutida IT guruhi IXTIYORIY bo'ladi.
 *
 * Marshrutning asosiy vazifasi — (BXM + local kod) dan HUDUDni aniqlash:
 * shu hudud zayavkaning muddatini (SLA) va uni kim bajarishini belgilaydi.
 * Guruh esa xizmatni bildiradi va uni foydalanuvchi zayavkada o'zi tanlaydi.
 *
 * Ilgari `team_id` majburiy edi, chunki har viloyatga avtomatik IT guruhi
 * yaratilardi. Ular endi yaratilmaydi, ya'ni majburiy ustun ofisni umuman
 * biriktirishga imkon bermasdi. Guruh faqat admin QO'LDA viloyat guruhi
 * ochgan holda ko'rsatiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_support_routes', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('office_support_routes', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable(false)->change();
        });
    }
};
