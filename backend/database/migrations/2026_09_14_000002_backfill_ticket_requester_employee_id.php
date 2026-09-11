<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `tickets.requester_employee_id` ni to'ldiradi.
 *
 * Ustun jadval yaratilganidan beri mavjud, lekin zayavka yaratishda hech
 * qachon yozilmagan — natijada `requesterEmployee` aloqasi doim null edi va
 * TicketResource'dagi telefon/pochta zaxiralari ishlamasdi. Qiymat
 * murojaatchining user yozuvidan olinadi (users.employee_id).
 *
 * To'ldirish foydalanuvchi bo'yicha aylanib bajariladi: `UPDATE ... JOIN`
 * SQLite'da (test bazasi) qo'llanmaydi — `SET` ichida qo'shilgan jadval
 * ustuni ko'rinmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('employee_id')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    DB::table('tickets')
                        ->where('requester_user_id', $user->id)
                        ->whereNull('requester_employee_id')
                        ->update(['requester_employee_id' => $user->employee_id]);
                }
            });
    }

    public function down(): void
    {
        // Ma'lumot tiklanmaydi: ustun oldin ham bo'sh edi, orqaga qaytarish
        // faqat shu to'ldirishni bekor qiladi.
        DB::table('tickets')->update(['requester_employee_id' => null]);
    }
};
