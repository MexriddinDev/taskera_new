<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE tickets ADD COLUMN IF NOT EXISTS search_vector tsvector
                GENERATED ALWAYS AS (
                    to_tsvector('english', coalesce(ticket_no, '') || ' ' || coalesce(subject, '') || ' ' || coalesce(description, ''))
                ) STORED;
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS tickets_search_vector_idx ON tickets USING GIN (search_vector);");

            DB::statement("
                ALTER TABLE knowledge_articles ADD COLUMN IF NOT EXISTS search_vector tsvector
                GENERATED ALWAYS AS (
                    to_tsvector('english', coalesce(article_no, '') || ' ' || coalesce(title, '') || ' ' || coalesce(summary, '') || ' ' || coalesce(content, ''))
                ) STORED;
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS knowledge_articles_search_vector_idx ON knowledge_articles USING GIN (search_vector);");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS tickets_search_vector_idx;");
            DB::statement("ALTER TABLE tickets DROP COLUMN IF EXISTS search_vector;");
            DB::statement("DROP INDEX IF EXISTS knowledge_articles_search_vector_idx;");
            DB::statement("ALTER TABLE knowledge_articles DROP COLUMN IF EXISTS search_vector;");
        }
    }
};
