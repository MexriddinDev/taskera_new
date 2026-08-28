<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['status_id', 'priority_id', 'created_at'], 'tickets_status_priority_created_idx');
            $table->index(['status_id', 'due_at'], 'tickets_due_status_idx');
            $table->index(['status_id', 'resolved_at'], 'tickets_resolved_status_idx');
            $table->index(['department_id', 'deleted_at'], 'tickets_dept_deleted_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['commentable_type', 'commentable_id', 'author_user_id'], 'comments_commentable_author_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['organization_id', 'assignee_user_id', 'status'], 'tasks_org_assignee_status_idx');
        });

        if (Schema::hasTable('ad_accounts')) {
            Schema::table('ad_accounts', function (Blueprint $table) {
                $table->index(['status', 'updated_at'], 'ad_accounts_status_updated_idx');
                $table->index(['pinfl', 'status'], 'ad_accounts_pinfl_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ad_accounts')) {
            Schema::table('ad_accounts', function (Blueprint $table) {
                $table->dropIndex('ad_accounts_status_updated_idx');
                $table->dropIndex('ad_accounts_pinfl_status_idx');
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_org_assignee_status_idx');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_commentable_author_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_priority_created_idx');
            $table->dropIndex('tickets_due_status_idx');
            $table->dropIndex('tickets_resolved_status_idx');
            $table->dropIndex('tickets_dept_deleted_idx');
        });
    }
};
