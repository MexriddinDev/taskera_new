<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('integration_type_id');
            $table->string('name', 255);
            $table->string('base_url', 1024)->nullable();
            $table->string('secret_ref', 255)->nullable();
            $table->jsonb('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('integration_type_id')->references('id')->on('integration_types')->restrictOnDelete();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('operation', 128);
            $table->uuid('correlation_id');
            $table->jsonb('request_meta')->nullable();
            $table->jsonb('response_meta')->nullable();
            $table->string('status', 16);
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at');

            $table->index(['integration_id', 'created_at']);
            $table->index(['correlation_id']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('url', 2048);
            $table->string('secret_ref', 255);
            $table->jsonb('subscribed_events');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event_type', 128);
            $table->jsonb('payload');
            $table->string('signature', 255);
            $table->string('status', 16)->default('PENDING');
            $table->smallInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->smallInteger('response_code')->nullable();
            $table->text('response_body_truncated')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['endpoint_id', 'event_id']);
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 128);
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();

            $table->index(['published_at', 'next_attempt_at']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->string('consumer', 128);
            $table->uuid('message_id');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->char('payload_hash', 64);

            $table->primary(['consumer', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integrations');
    }
};
