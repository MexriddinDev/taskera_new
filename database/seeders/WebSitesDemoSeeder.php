<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebSitesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('WebSitesDemoSeeder completed (mock tickets disabled).');
    }
}

