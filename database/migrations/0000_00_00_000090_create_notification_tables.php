<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->unsignedSmallInteger('channel_id');
            $table->unsignedSmallInteger('locale_id');
            $table->text('subject_template')->nullable();
            $table->text('body_template');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('channel_id')->references('id')->on('notification_channels')->restrictOnDelete();
            $table->foreign('locale_id')->references('id')->on('locales')->restrictOnDelete();
            $table->unique(['organization_id', 'code', 'channel_id', 'locale_id', 'version'], 'ntf_tmpl_org_code_chan_loc_ver_uq');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 128);
            $table->string('notifiable_type', 100);
            $table->unsignedBigInteger('notifiable_id');
            $table->string('template_code', 64);
            $table->string('title', 500);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->nullable();

            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
            $table->index(['correlation_id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedSmallInteger('channel_id');
            $table->string('recipient', 500);
            $table->string('status', 16)->default('PENDING');
            $table->string('provider_message_id', 255)->nullable();
            $table->smallInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->timestampsTz();

            $table->foreign('channel_id')->references('id')->on('notification_channels')->restrictOnDelete();
            $table->unique(['notification_id', 'channel_id', 'recipient'], 'ntf_dlv_notif_chan_rec_uq');
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 128);
            $table->unsignedSmallInteger('channel_id');
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('quiet_hours')->nullable();

            $table->foreign('channel_id')->references('id')->on('notification_channels')->restrictOnDelete();
            $table->primary(['user_id', 'event_type', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
    }
};
