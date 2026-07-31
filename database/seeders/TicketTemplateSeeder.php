<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Organization\Infrastructure\Eloquent\TicketTemplate;
use Illuminate\Database\Seeder;

class TicketTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'team_id' => 1, // Texnik guruh
                'name' => 'Yangi xodim kompyuterini sozlash',
                'content' => "Yangi xodim uchun kompyuterni sozlash kerak.\n\nKompyuter holati:\n- Yangi kompyuter / mavjud kompyuter: \n- O'rnatiladigan dasturlar: \n- Tarmoqqa ulanish kerakmi: ha/yo'q\n- Qo'shimcha talablar: ",
                'sort_order' => 1,
            ],
            [
                'team_id' => 1, // Texnik guruh
                'name' => 'Kompyuter ishlamayapti',
                'content' => "Kompyuterim ishlamayapti.\n\nMuammo turi:\n- Kompyuter yoqilmayapti / ko'k ekran / sekin ishlayapti\n- Xato xabari: \n- Qachondan beri: \n- Xona / bo'lim: ",
                'sort_order' => 2,
            ],
            [
                'team_id' => 1, // Texnik guruh
                'name' => 'Printer / nusxa mashina muammosi',
                'content' => "Printer (nusxa mashina) bilan muammo bor.\n\n- Qurilma nomi / modeli: \n- Muammo: chop etmayapti / qog'oz tiqildi / rang noto'g'ri\n- Qaysi kompyuterdan ishlatiladi: \n- Xona / bo'lim: ",
                'sort_order' => 3,
            ],
            [
                'team_id' => 1, // Texnik guruh
                'name' => 'Internet / tarmoq muammosi',
                'content' => "Internet yoki tarmoqda muammo bor.\n\n- Muammo: internet umuman yo'q / sekin ishlayapti / tarmoq fayllari ochilmayapti\n- Faqat shu kompyuterdami yoki butun bo'limda: \n- Xona / bo'lim: ",
                'sort_order' => 4,
            ],
            [
                'team_id' => 1, // Texnik guruh
                'name' => 'Dastur o\'rnatish / yangilash',
                'content' => "Dastur o'rnatish yoki yangilash kerak.\n\n- Dastur nomi: \n- O'rnatish / yangilash: \n- Litsenziya kerakmi: \n- Kompyuter / xona: ",
                'sort_order' => 5,
            ],
            [
                'team_id' => 2, // NOC monitoring guruhi
                'name' => 'Server / monitoring signali tushib qolgan',
                'content' => "Monitoring signali yoki server holati bo'yicha muammo bor.\n\n- Qurilma / tizim nomi: \n- Signal qachondan beri tushgan: \n- Oxirgi normal holat vaqti: \n- Qo'shimcha ma'lumot: ",
                'sort_order' => 1,
            ],
            [
                'team_id' => 3, // Backend dasturchilar guruhi
                'name' => 'API / tizim xatosi',
                'content' => "Tizimda API yoki dasturiy xato yuz berdi.\n\n- Tizim nomi: \n- Xato xabari / kod: \n- Qaysi bo'limda / funksiyada: \n- Qayta takrorlash ketma-ketligi: ",
                'sort_order' => 1,
            ],
            [
                'team_id' => 4, // Frontend dasturchilar guruhi
                'name' => 'Sahifa / interfeys muammosi',
                'content' => "Veb-sahifa yoki interfeysda muammo bor.\n\n- Sahifa manzili (URL): \n- Muammo: sahifa ochilmayapti / xatolik / dizayn buzilishi\n- Brauzer va ekran hajmi: \n- Skrinshot biriktirildi: ",
                'sort_order' => 1,
            ],
        ];

        foreach ($templates as $template) {
            TicketTemplate::updateOrCreate(
                ['team_id' => $template['team_id'], 'name' => $template['name']],
                $template
            );
        }
    }
}
