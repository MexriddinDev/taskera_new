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
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->join('users', 'users.id', '=', 'tickets.requester_user_id')
            ->whereNull('tickets.requester_employee_id')
            ->whereNotNull('users.employee_id')
            ->update(['tickets.requester_employee_id' => DB::raw('users.employee_id')]);
    }

    public function down(): void
    {
        // Ma'lumot tiklanmaydi: ustun oldin ham bo'sh edi, orqaga qaytarish
        // faqat shu to'ldirishni bekor qiladi.
        DB::table('tickets')->update(['requester_employee_id' => null]);
    }
};
