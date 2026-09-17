<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_employee_conditions', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('code', 100)->index();
            $table->string('name_ru', 255);
            $table->string('name_uz', 255);
            $table->char('condition_type', 4)->default('A');
            $table->boolean('is_working')->default(false);
            $table->boolean('can_open_ad')->default(false);
            $table->unsignedSmallInteger('order_by')->default(0);
            $table->string('rowid_ref', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        $conditions = [
            ['id' => 1, 'code' => 'SA', 'name_ru' => 'Утвержден', 'name_uz' => 'Tasdiqlangan', 'condition_type' => 'S', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 1, 'rowid_ref' => 'AABpVBADQAAAtOrAAA', 'notes' => 'Hujjat holati: Tasdiqlangan'],
            ['id' => 2, 'code' => 'SV', 'name_ru' => 'Введен', 'name_uz' => 'Kiritilgan', 'condition_type' => 'S', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 2, 'rowid_ref' => 'AABpVBADQAAAtOrAAB', 'notes' => 'Hujjat holati: Kiritilgan'],
            ['id' => 3, 'code' => 'SU', 'name_ru' => 'На утверждения', 'name_uz' => 'Tasdiqlashda', 'condition_type' => 'S', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 3, 'rowid_ref' => 'AABpVBADQAAAtOrAAC', 'notes' => 'Hujjat holati: Tasdiqlash kutilmoqda'],
            ['id' => 4, 'code' => 'SO', 'name_ru' => 'Отклонен', 'name_uz' => 'Rad etilgan', 'condition_type' => 'S', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 4, 'rowid_ref' => 'AABpVBADQAAAtOrAAD', 'notes' => 'Hujjat holati: Rad etilgan'],
            ['id' => 5, 'code' => 'A', 'name_ru' => 'Рабочие', 'name_uz' => 'Ishlayotgan xodimlar', 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 5, 'rowid_ref' => 'AABpVBADQAAAtOrAAE', 'notes' => 'Asosiy shtatdagi faol ishlayotgan xodimlar'],
            ['id' => 6, 'code' => "A','AP','DB','OD','OF','I','OU','AO','OT','OS','OB','K','B','AS','AK','AT", 'name_ru' => 'Рабочие(Все)', 'name_uz' => 'Barcha ishlayotganlar guruhi', 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => false, 'order_by' => 6, 'rowid_ref' => 'AABpVBADQAAAtOrAAF', 'notes' => "Barcha faol va ta'tildagi xodimlar yig'ma to'plami"],
            ['id' => 7, 'code' => 'AP', 'name_ru' => 'Совместители', 'name_uz' => "O'rindoshlar (qo'shimcha ishlovchilar)", 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 7, 'rowid_ref' => 'AABpVBADQAAAtOrAAG', 'notes' => "Bir necha lavozimda o'rindoshlik asosida ishlovchilar"],
            ['id' => 8, 'code' => 'P', 'name_ru' => 'Уволенные', 'name_uz' => "Ishdan bo'shatilganlar", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 8, 'rowid_ref' => 'AABpVBADQAAAtOrAAH', 'notes' => 'Mehnat shartnomasi bekor qilingan xodimlar'],
            ['id' => 9, 'code' => 'PF', 'name_ru' => 'Увол. (пер. филиалу)', 'name_uz' => "Bo'shatilgan (boshqa filialga o'tgan)", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 9, 'rowid_ref' => 'AABpVBADQAAAtOrAAI', 'notes' => "Boshqa filialga o'tishi sababli bo'shatilgan"],
            ['id' => 10, 'code' => 'PO', 'name_ru' => 'Отстранен', 'name_uz' => 'Vazifasidan chetlashtirilgan', 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 10, 'rowid_ref' => 'AABpVBADQAAAtOrAAJ', 'notes' => 'Vaqtinchalik ishdan chetlatilgan xodim'],
            ['id' => 11, 'code' => 'DB', 'name_ru' => 'Декрет больничный', 'name_uz' => "Homiladorlik va tug'ish ta'tili", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 11, 'rowid_ref' => 'AABpVBADQAAAtOrAAK', 'notes' => "Dekret kasallik varaqasi asosida ta'tilda"],
            ['id' => 12, 'code' => 'OD', 'name_ru' => 'Декрет_2', 'name_uz' => "Bola parvarishlash ta'tili (2 yoshgacha)", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 12, 'rowid_ref' => 'AABpVBADQAAAtOrAAL', 'notes' => "2 yoshgacha bola parvarishi ta'tili"],
            ['id' => 13, 'code' => 'OF', 'name_ru' => 'Декрет_3', 'name_uz' => "Bola parvarishlash ta'tili (3 yoshgacha)", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 13, 'rowid_ref' => 'AABpVBADQAAAtOrAAM', 'notes' => "3 yoshgacha bola parvarishi ta'tili"],
            ['id' => 14, 'code' => 'OU', 'name_ru' => 'Ученический отп.', 'name_uz' => "O'quv ta'tili", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 14, 'rowid_ref' => 'AABpVBADQAAAtOrAAN', 'notes' => "Malaka oshirish yoki ta'lim muassasasida o'qish ta'tili"],
            ['id' => 15, 'code' => 'AO', 'name_ru' => 'Академик отп.', 'name_uz' => "Akademik ta'til", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 15, 'rowid_ref' => 'AABpVBADQAAAtOrAAO', 'notes' => "Akademik ta'tildagi xodim"],
            ['id' => 16, 'code' => 'I', 'name_ru' => 'Воинская служба', 'name_uz' => 'Harbiy xizmat', 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 16, 'rowid_ref' => 'AABpVBADQAAAtOrAAP', 'notes' => 'Harbiy xizmatga chaqirilgan xodim'],
            ['id' => 17, 'code' => 'OT', 'name_ru' => 'Трудовой отп.', 'name_uz' => "Mehnat ta'tili", 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 17, 'rowid_ref' => 'AABpVBADQAAAtOrAAQ', 'notes' => "Yillik asosiy yoki qo'shimcha mehnat ta'tilida"],
            ['id' => 18, 'code' => 'OB', 'name_ru' => 'Без содержания', 'name_uz' => "Ish haqi saqlanmagan ta'tilda", 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 18, 'rowid_ref' => 'AABpVBADQAAAtOrAAR', 'notes' => "O'z hisobidan ta'tilda (ish haqi saqlanmaydi)"],
            ['id' => 19, 'code' => 'OS', 'name_ru' => 'С содержания', 'name_uz' => "Ish haqi saqlangan holda ta'tilda", 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 19, 'rowid_ref' => 'AABpVBADQAAAtOrAAS', 'notes' => "Kafolatlangan ta'minot bilan ta'tilda"],
            ['id' => 20, 'code' => 'KA', 'name_ru' => 'Рабочие (не штат.)', 'name_uz' => 'Shtatdan tashqari ishlovchilar', 'condition_type' => 'K', 'is_working' => true, 'can_open_ad' => false, 'order_by' => 20, 'rowid_ref' => 'AABpVBADQAAAtOrAAT', 'notes' => 'Fuqarolik-huquqiy shartnoma asosida shtatdan tashqari ishlovchi'],
            ['id' => 21, 'code' => 'KP', 'name_ru' => 'Уволенные (не штат.)', 'name_uz' => "Bo'shatilgan (shtatdan tashqari)", 'condition_type' => 'K', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 21, 'rowid_ref' => 'AABpVBADQAAAtOrAAU', 'notes' => 'Shartnomasi yakunlangan shtatdan tashqari xodim'],
            ['id' => 22, 'code' => 'KO', 'name_ru' => 'Отстраненные (не штат.)', 'name_uz' => 'Chetlatilgan (shtatdan tashqari)', 'condition_type' => 'K', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 22, 'rowid_ref' => 'AABpVBADQAAAtOrAAV', 'notes' => 'Chetlashtirilgan shtatdan tashqari xodim'],
            ['id' => 23, 'code' => 'SF', 'name_ru' => 'Перевод другой филиал', 'name_uz' => "Boshqa filialga o'tkazish jarayonida", 'condition_type' => 'F', 'is_working' => true, 'can_open_ad' => false, 'order_by' => 23, 'rowid_ref' => 'AABpVBADQAAAtOrAAW', 'notes' => 'Filiallararo rotatsiya jarayonidagi xodim'],
            ['id' => 24, 'code' => 'K', 'name_ru' => 'Командировка', 'name_uz' => 'Xizmat safari (komandirovka)', 'condition_type' => 'K', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 24, 'rowid_ref' => 'AABpVBADQAAAtOrAAX', 'notes' => "Xizmat safari munosabati bilan safarda bo'lgan xodim"],
            ['id' => 25, 'code' => 'B', 'name_ru' => 'Болничные', 'name_uz' => "Kasallik ta'tilida (bolnichniy)", 'condition_type' => 'K', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 25, 'rowid_ref' => 'AABpVBADQAAAtOrAAY', 'notes' => "Vaqtinchalik mehnatga layoqatsizlik varaqasida"],
            ['id' => 26, 'code' => 'AS', 'name_ru' => 'Стажеры', 'name_uz' => 'Stajyorlar', 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 26, 'rowid_ref' => 'AABpVBADQAAAtOrAAZ', 'notes' => "Bankda stajirovka o'tayotgan xodimlar"],
            ['id' => 27, 'code' => 'AK', 'name_ru' => 'Совет банка', 'name_uz' => "Bank kengashi a'zosi", 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 27, 'rowid_ref' => 'AABpVBADQAAAtOrAAa', 'notes' => "Bank kengashi rahbariyat a'zosi"],
            ['id' => 28, 'code' => 'AT', 'name_ru' => 'Ревизионная комиссия', 'name_uz' => "Taftish komissiyasi a'zosi", 'condition_type' => 'A', 'is_working' => true, 'can_open_ad' => true, 'order_by' => 28, 'rowid_ref' => 'AABpVBADQAAAtOrAAb', 'notes' => "Taftish komissiyasi a'zosi"],
            ['id' => 29, 'code' => 'PR', 'name_ru' => 'Прочие', 'name_uz' => 'Boshqa toifalar', 'condition_type' => 'A', 'is_working' => false, 'can_open_ad' => false, 'order_by' => 29, 'rowid_ref' => 'AABpVBADQAAAtOrAAc', 'notes' => 'Boshqa maxsus toifadagi holatlar'],
        ];

        foreach ($conditions as &$item) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }

        \Illuminate\Support\Facades\DB::table('hr_employee_conditions')->insert($conditions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employee_conditions');
    }
};
