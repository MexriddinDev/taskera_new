<?php

namespace Tests\Feature\ITSM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        DB::table('organizations')->insert(['id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Catalog', 'code' => 'CAT',
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_catalog_services_are_imported_once(): void
    {
        $this->seed(\Database\Seeders\ServiceCatalogSeeder::class);
        $this->seed(\Database\Seeders\ServiceCatalogSeeder::class);

        $this->assertSame(25, DB::table('services')->count());
        $this->assertSame(25, DB::table('service_offerings')->count());
    }
}
