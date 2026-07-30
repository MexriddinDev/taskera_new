<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Enable RLS for multi-tenancy / organization level security
            $tablesWithOrg = [
                'tickets', 'comments', 'attachments', 'assets', 'knowledge_articles',
                'problems', 'changes', 'workflows', 'automation_rules', 'integrations'
            ];

            foreach ($tablesWithOrg as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
                DB::statement("
                    CREATE POLICY {$table}_org_policy ON {$table}
                    FOR ALL
                    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::bigint);
                ");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $tablesWithOrg = [
                'tickets', 'comments', 'attachments', 'assets', 'knowledge_articles',
                'problems', 'changes', 'workflows', 'automation_rules', 'integrations'
            ];

            foreach ($tablesWithOrg as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_org_policy ON {$table};");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
            }
        }
    }
};
