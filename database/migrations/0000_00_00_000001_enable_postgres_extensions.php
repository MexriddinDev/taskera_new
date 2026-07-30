<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
            DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm";');
            DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gist";');
        }
    }

    public function down(): void
    {
        // Extension drop omitted for safety in shared environments
    }
};
