<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('ticket_no', 32);
            $table->string('ticket_type', 32)->default('INCIDENT');
            $table->string('subject', 500);
            $table->text('description');
            $table->unsignedSmallInteger('status_id');
            $table->unsignedSmallInteger('priority_id');
            $table->unsignedSmallInteger('source_id');
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('service_offering_id')->nullable()->constrained('service_offerings')->nullOnDelete();
            $table->foreignId('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->smallInteger('impact')->nullable();
            $table->smallInteger('urgency')->nullable();
            $table->timestampTz('first_response_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->foreignId('resolution_code_id')->nullable()->constrained('resolution_codes')->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->string('telegram_chat_id', 32)->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('external_reference', 128)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('status_id')->references('id')->on('ticket_statuses')->restrictOnDelete();
            $table->foreign('priority_id')->references('id')->on('ticket_priorities')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('ticket_sources')->restrictOnDelete();
            $table->unique(['organization_id', 'ticket_no']);
            $table->index(['organization_id', 'status_id', 'priority_id', 'created_at']);
            $table->index(['assigned_user_id', 'status_id', 'updated_at']);
            $table->index(['assigned_team_id', 'status_id', 'priority_id']);
            $table->index(['requester_user_id', 'created_at']);
        });

        Schema::create('ticket_watchers', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['ticket_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 64);
            $table->string('color', 16)->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['ticket_id', 'tag_id']);
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('assignment_type', 32);
            $table->text('reason')->nullable();
            $table->timestampTz('assigned_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('unassigned_at')->nullable();

            $table->index(['ticket_id', 'assigned_at']);
            $table->index(['assignee_user_id', 'unassigned_at']);
        });

        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedSmallInteger('from_status_id')->nullable();
            $table->unsignedSmallInteger('to_status_id');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('source_id');
            $table->string('action', 64);
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->uuid('correlation_id');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('from_status_id')->references('id')->on('ticket_statuses')->nullOnDelete();
            $table->foreign('to_status_id')->references('id')->on('ticket_statuses')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('ticket_sources')->restrictOnDelete();
            $table->index(['ticket_id', 'created_at']);
            $table->index(['correlation_id']);
        });

        Schema::create('ticket_priority_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedSmallInteger('from_priority_id')->nullable();
            $table->unsignedSmallInteger('to_priority_id');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('source_id');
            $table->timestampTz('created_at');

            $table->foreign('from_priority_id')->references('id')->on('ticket_priorities')->nullOnDelete();
            $table->foreign('to_priority_id')->references('id')->on('ticket_priorities')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('ticket_sources')->restrictOnDelete();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('source_id');
            $table->timestampTz('created_at');

            $table->foreign('source_id')->references('id')->on('ticket_sources')->restrictOnDelete();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_history');
        Schema::dropIfExists('ticket_priority_history');
        Schema::dropIfExists('ticket_status_history');
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('tickets');
    }
};
