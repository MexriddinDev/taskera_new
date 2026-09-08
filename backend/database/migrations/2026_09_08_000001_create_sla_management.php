<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $t) {
            $t->string('publication_status', 16)->default('DRAFT');
            $t->json('draft_config')->nullable();
            $t->unsignedBigInteger('published_version_id')->nullable();
        });
        Schema::create('sla_policy_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sla_policy_id')->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->json('config');
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('published_at');
            $t->unique(['sla_policy_id', 'number']);
        });
        Schema::create('ticket_sla_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('policy_version_id')->constrained('sla_policy_versions')->restrictOnDelete();
            $t->unsignedInteger('cycle')->default(1);
            $t->timestampTz('created_at');
            $t->timestampTz('ended_at')->nullable();
            $t->unique(['ticket_id', 'cycle']);
        });
        Schema::create('ticket_sla_instances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('run_id')->constrained('ticket_sla_runs')->restrictOnDelete();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('metric', 32);
            $t->string('status', 16)->default('PENDING');
            $t->unsignedInteger('target_minutes');
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('due_at')->nullable();
            $t->timestampTz('next_escalation_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampTz('breached_at')->nullable();
            $t->timestampTz('paused_at')->nullable();
            $t->bigInteger('remaining_seconds')->nullable();
            $t->unsignedBigInteger('paused_seconds')->default(0);
            $t->unsignedInteger('extension_minutes')->default(0);
            $t->unsignedInteger('escalation_index')->default(0);
            $t->timestampsTz();
            $t->unique(['run_id', 'metric']);
            $t->index(['status', 'next_escalation_at'], 'sla_instances_next_idx');
            $t->index(['organization_id', 'status', 'due_at'], 'sla_instances_monitor_idx');
        });
        Schema::create('sla_instance_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instance_id')->constrained('ticket_sla_instances')->restrictOnDelete();
            $t->string('event_type', 32);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('data')->nullable();
            $t->timestampTz('created_at');
            $t->index(['instance_id', 'created_at']);
        });
        Schema::create('ticket_sla_extensions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instance_id')->constrained('ticket_sla_instances')->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('minutes');
            $t->string('reason_code', 64);
            $t->text('reason');
            $t->string('status', 16)->default('PENDING');
            $t->text('decision_reason')->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->timestampsTz();
            $t->index(['approver_id', 'status']);
        });
        Schema::create('sla_escalation_outbox', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instance_id')->constrained('ticket_sla_instances')->restrictOnDelete();
            $t->unsignedInteger('step');
            $t->json('rule');
            $t->timestampTz('processed_at')->nullable();
            $t->timestampTz('created_at');
            $t->unique(['instance_id', 'step']);
            $t->index(['processed_at', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['sla_escalation_outbox', 'ticket_sla_extensions', 'sla_instance_events', 'ticket_sla_instances', 'ticket_sla_runs', 'sla_policy_versions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('sla_policies', fn (Blueprint $t) => $t->dropColumn(['publication_status', 'draft_config', 'published_version_id']));
    }
};
