<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('locales')->insertOrIgnore([
            ['id' => 1, 'code' => 'uz', 'name' => "O'zbekcha", 'is_active' => true, 'sort_order' => 1],
            ['id' => 2, 'code' => 'ru', 'name' => 'Русский', 'is_active' => true, 'sort_order' => 2],
            ['id' => 3, 'code' => 'en', 'name' => 'English', 'is_active' => true, 'sort_order' => 3],
        ]);

        DB::table('timezones')->insertOrIgnore([
            ['id' => 1, 'name' => 'Asia/Tashkent', 'utc_offset_hint' => 5, 'is_active' => true],
            ['id' => 2, 'name' => 'UTC', 'utc_offset_hint' => 0, 'is_active' => true],
        ]);

        DB::table('employment_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'ACTIVE', 'name' => 'Active', 'can_login' => true, 'is_terminal' => false, 'sort_order' => 1],
            ['id' => 2, 'code' => 'SUSPENDED', 'name' => 'Suspended', 'can_login' => false, 'is_terminal' => false, 'sort_order' => 2],
            ['id' => 3, 'code' => 'TERMINATED', 'name' => 'Terminated', 'can_login' => false, 'is_terminal' => true, 'sort_order' => 3],
            ['id' => 4, 'code' => 'LEAVE', 'name' => 'On Leave', 'can_login' => false, 'is_terminal' => false, 'sort_order' => 4],
        ]);

        DB::table('ticket_statuses')->insertOrIgnore([
            ['id' => 1, 'code' => 'NEW', 'name' => 'Yangi', 'status_group' => 'OPEN', 'is_initial' => true, 'is_terminal' => false, 'pauses_sla' => false, 'customer_visible' => true, 'sort_order' => 1, 'color' => '#3B82F6'],
            ['id' => 2, 'code' => 'OPEN', 'name' => 'Ochiq', 'status_group' => 'OPEN', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => false, 'customer_visible' => true, 'sort_order' => 2, 'color' => '#10B981'],
            ['id' => 3, 'code' => 'ASSIGNED', 'name' => 'Biriktirilgan', 'status_group' => 'OPEN', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => false, 'customer_visible' => true, 'sort_order' => 3, 'color' => '#6366F1'],
            ['id' => 4, 'code' => 'IN_PROGRESS', 'name' => 'Jarayonda', 'status_group' => 'IN_PROGRESS', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => false, 'customer_visible' => true, 'sort_order' => 4, 'color' => '#8B5CF6'],
            ['id' => 5, 'code' => 'WAITING_USER', 'name' => 'Foydalanuvchi kutilmoqda', 'status_group' => 'PENDING', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 5, 'color' => '#F59E0B'],
            ['id' => 6, 'code' => 'WAITING_VENDOR', 'name' => 'Vektor/Yetkazib beruvchi kutilmoqda', 'status_group' => 'PENDING', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 6, 'color' => '#F97316'],
            ['id' => 7, 'code' => 'RESOLVED', 'name' => 'Hal qilindi', 'status_group' => 'RESOLVED', 'is_initial' => false, 'is_terminal' => false, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 7, 'color' => '#22C55E'],
            ['id' => 8, 'code' => 'CLOSED', 'name' => 'Yopildi', 'status_group' => 'CLOSED', 'is_initial' => false, 'is_terminal' => true, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 8, 'color' => '#64748B'],
            ['id' => 9, 'code' => 'REJECTED', 'name' => 'Radd etildi', 'status_group' => 'CLOSED', 'is_initial' => false, 'is_terminal' => true, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 9, 'color' => '#EF4444'],
            ['id' => 10, 'code' => 'CANCELLED', 'name' => 'Bekor qilindi', 'status_group' => 'CLOSED', 'is_initial' => false, 'is_terminal' => true, 'pauses_sla' => true, 'customer_visible' => true, 'sort_order' => 10, 'color' => '#9CA3AF'],
        ]);

        DB::table('ticket_priorities')->insertOrIgnore([
            ['id' => 1, 'code' => 'CRITICAL', 'name' => 'Kritik', 'weight' => 100, 'color' => '#EF4444'],
            ['id' => 2, 'code' => 'HIGH', 'name' => 'Yuqori', 'weight' => 75, 'color' => '#F97316'],
            ['id' => 3, 'code' => 'MEDIUM', 'name' => "O'rta", 'weight' => 50, 'color' => '#F59E0B'],
            ['id' => 4, 'code' => 'LOW', 'name' => 'Past', 'weight' => 25, 'color' => '#10B981'],
        ]);

        DB::table('ticket_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web Portal'],
            ['id' => 2, 'code' => 'TELEGRAM', 'name' => 'Telegram Bot'],
            ['id' => 3, 'code' => 'EMAIL', 'name' => 'Email Parser'],
            ['id' => 4, 'code' => 'API', 'name' => 'REST API'],
            ['id' => 5, 'code' => 'MONITORING', 'name' => 'Monitoring Alert'],
        ]);

        DB::table('comment_types')->insertOrIgnore([
            ['id' => 1, 'code' => 'PUBLIC', 'name' => 'Ommaviy', 'customer_visible' => true],
            ['id' => 2, 'code' => 'INTERNAL', 'name' => 'Ichki izoh', 'customer_visible' => false],
        ]);

        DB::table('attachment_types')->insertOrIgnore([
            ['id' => 1, 'code' => 'IMAGE', 'name' => 'Rasm / Screenshot', 'mime_patterns' => json_encode(['image/*']), 'max_size_bytes' => 10485760, 'is_active' => true],
            ['id' => 2, 'code' => 'VIDEO', 'name' => 'Video', 'mime_patterns' => json_encode(['video/*']), 'max_size_bytes' => 52428800, 'is_active' => true],
            ['id' => 3, 'code' => 'AUDIO', 'name' => 'Ovozli xabar', 'mime_patterns' => json_encode(['audio/*']), 'max_size_bytes' => 10485760, 'is_active' => true],
            ['id' => 4, 'code' => 'FILE', 'name' => 'Fayl', 'mime_patterns' => json_encode([]), 'max_size_bytes' => 10485760, 'is_active' => true],
        ]);

        DB::table('comment_sources')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web Portal'],
            ['id' => 2, 'code' => 'TELEGRAM', 'name' => 'Telegram Bot'],
            ['id' => 3, 'code' => 'EMAIL', 'name' => 'Email Reply'],
            ['id' => 4, 'code' => 'SYSTEM', 'name' => 'Tizim bildirishnomasi'],
        ]);

        DB::table('notification_channels')->insertOrIgnore([
            ['id' => 1, 'code' => 'WEB', 'name' => 'Web Notifications'],
            ['id' => 2, 'code' => 'TELEGRAM', 'name' => 'Telegram Bot'],
            ['id' => 3, 'code' => 'EMAIL', 'name' => 'Email'],
            ['id' => 4, 'code' => 'PUSH', 'name' => 'Mobile Push'],
            ['id' => 5, 'code' => 'SMS', 'name' => 'SMS Gateway'],
        ]);
    }
}
