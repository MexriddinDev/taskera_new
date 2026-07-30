<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->unsignedSmallInteger('timezone_id');
            $table->boolean('is_24x7')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('timezone_id')->references('id')->on('timezones')->restrictOnDelete();
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('business_calendars')->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_working')->default(true);

            $table->unique(['calendar_id', 'weekday', 'start_time']);
        });

        Schema::create('calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('business_calendars')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 255);
            $table->boolean('is_working_override')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->unique(['calendar_id', 'holiday_date']);
        });

        Schema::create('sla_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_policy_id')->constrained('sla_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority_id');
            $table->string('metric_type', 32);
            $table->integer('target_minutes');
            $table->integer('warning_minutes')->nullable();
            $table->integer('escalation_minutes')->nullable();
            $table->jsonb('pause_statuses')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreign('priority_id')->references('id')->on('ticket_priorities')->restrictOnDelete();
            $table->unique(['sla_policy_id', 'priority_id', 'metric_type']);
        });

        Schema::create('ticket_slas', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('sla_target_id')->constrained('sla_targets')->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('due_at');
            $table->timestampTz('warning_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('breached_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->bigInteger('paused_seconds')->default(0);
            $table->bigInteger('remaining_seconds')->nullable();
            $table->string('status', 16)->default('RUNNING');
            $table->jsonb('calculation_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();

            $table->unique(['ticket_id', 'sla_target_id']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('ticket_sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sla_id')->constrained('ticket_slas')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->timestampTz('event_at');
            $table->unsignedSmallInteger('trigger_status_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();

            $table->index(['ticket_sla_id', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_events');
        Schema::dropIfExists('ticket_slas');
        Schema::dropIfExists('sla_targets');
        Schema::dropIfExists('calendar_holidays');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('business_calendars');
    }
};
