<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            // Huquqlar va rollar (admin / support / spectator / user) shu yerda
            // yaratiladi. Ilgari ular ro'yxatda yo'q edi — natijada toza
            // o'rnatishdan keyin RBAC bo'limida faqat demo rollari qolardi.
            PermissionsSeeder::class,
            RolesSeeder::class,
            ServiceCatalogSeeder::class,
            EnterpriseDemoSeeder::class,
        ]);
    }
}
