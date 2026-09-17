<?php

namespace Database\Seeders;

use App\Support\HrEmployeeState;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HrEmployeeConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        foreach (HrEmployeeState::all() as $code => $item) {
            DB::table('hr_employee_conditions')->updateOrInsert(
                ['id' => $item['id']],
                [
                    'code' => $code,
                    'name_ru' => $item['name_ru'],
                    'name_uz' => $item['name_uz'],
                    'condition_type' => $item['type'],
                    'is_working' => $item['is_working'],
                    'can_open_ad' => $item['can_open_ad'],
                    'order_by' => $item['id'],
                    'notes' => $item['notes'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
