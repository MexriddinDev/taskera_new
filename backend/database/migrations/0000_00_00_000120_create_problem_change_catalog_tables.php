<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('problem_no', 32);
            $table->string('title', 500);
            $table->text('description');
            $table->string('status', 32)->default('NEW');
            $table->unsignedSmallInteger('priority_id');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('known_error')->default(false);
            $table->text('root_cause')->nullable();
            $table->text('workaround')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('priority_id')->references('id')->on('ticket_priorities')->restrictOnDelete();
            $table->unique(['organization_id', 'problem_no']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->foreignId('ticket_id')->primary()->constrained('tickets')->cascadeOnDelete();
            $table->smallInteger('impact')->nullable();
            $table->smallInteger('urgency')->nullable();
            $table->boolean('major_incident')->default(false);
            $table->timestampTz('detected_at')->nullable();
            $table->timestampTz('outage_start_at')->nullable();
            $table->timestampTz('outage_end_at')->nullable();
            $table->integer('affected_user_count')->nullable();
            $table->foreignId('root_cause_problem_id')->nullable()->constrained('problems')->nullOnDelete();
        });

        Schema::create('problem_tickets', function (Blueprint $table) {
            $table->foreignId('problem_id')->constrained('problems')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('relation_type', 32)->default('CAUSED_BY');
            $table->timestampTz('created_at');

            $table->primary(['problem_id', 'ticket_id']);
        });

        Schema::create('changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('change_no', 32);
            $table->string('title', 500);
            $table->text('description');
            $table->string('change_type', 32)->default('STANDARD');
            $table->smallInteger('risk_level')->default(1);
            $table->smallInteger('impact')->default(1);
            $table->string('status', 32)->default('DRAFT');
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('planned_start_at');
            $table->timestampTz('planned_end_at');
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();
            $table->text('backout_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->string('approval_status', 32)->default('PENDING');
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'change_no']);
            $table->index(['status', 'planned_start_at']);
        });

        Schema::create('change_assets', function (Blueprint $table) {
            $table->foreignId('change_id')->constrained('changes')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('impact_type', 32)->default('MODIFIED');

            $table->primary(['change_id', 'asset_id']);
        });

        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('recurrence_rule', 500)->nullable();
            $table->string('status', 16)->default('SCHEDULED');
            $table->foreignId('change_id')->nullable()->constrained('changes')->nullOnDelete();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('service_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_offering_id')->nullable()->constrained('service_offerings')->nullOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->jsonb('form_schema')->nullable();
            $table->unsignedBigInteger('fulfillment_workflow_id')->nullable();
            $table->unsignedBigInteger('approval_workflow_id')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->foreignId('ticket_id')->primary()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('service_catalog_items')->restrictOnDelete();
            $table->jsonb('form_data')->nullable();
            $table->foreignId('requested_for_user_id')->constrained('users')->restrictOnDelete();
            $table->string('fulfillment_status', 32)->default('SUBMITTED');
            $table->timestampTz('completed_at')->nullable();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('workflow_run_id')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->integer('sequence');
            $table->string('approver_type', 32)->default('USER');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status', 16)->default('PENDING');
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decision_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();

            $table->unique(['approval_request_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('service_catalog_items');
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('change_assets');
        Schema::dropIfExists('changes');
        Schema::dropIfExists('problem_tickets');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('problems');
    }
};
