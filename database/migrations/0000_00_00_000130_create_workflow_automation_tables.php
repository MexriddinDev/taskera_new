<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('entity_type', 64);
            $table->integer('version')->default(1);
            $table->jsonb('definition');
            $table->string('status', 16)->default('DRAFT');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code', 'version']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->restrictOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 16)->default('RUNNING');
            $table->jsonb('context')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('workflow_step_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('step_key', 128);
            $table->string('step_type', 64);
            $table->string('status', 16)->default('PENDING');
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->smallInteger('attempt_count')->default(1);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->unique(['workflow_run_id', 'step_key', 'attempt_count']);
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('event_type', 128);
            $table->jsonb('conditions')->nullable();
            $table->jsonb('actions');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->index(['organization_id', 'event_type', 'is_active', 'priority'], 'auto_rules_org_event_act_prio_idx');
        });

        Schema::create('automation_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->uuid('event_id');
            $table->string('status', 16)->default('SUCCESS');
            $table->jsonb('input')->nullable();
            $table->jsonb('result')->nullable();
            $table->timestampTz('executed_at');
            $table->text('error_message')->nullable();

            $table->unique(['rule_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('workflow_step_runs');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
