<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('timezones', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 64)->unique();
            $table->smallInteger('utc_offset_hint')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('employment_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('can_login')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('status_group', 16);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('pauses_sla')->default(false);
            $table->boolean('customer_visible')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->string('color', 16)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('ticket_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('from_status_id');
            $table->unsignedSmallInteger('to_status_id');
            $table->string('required_permission', 125)->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->boolean('requires_resolution')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreign('from_status_id')->references('id')->on('ticket_statuses')->restrictOnDelete();
            $table->foreign('to_status_id')->references('id')->on('ticket_statuses')->restrictOnDelete();
            $table->unique(['from_status_id', 'to_status_id']);
        });

        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->smallInteger('weight')->default(0);
            $table->string('color', 16);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('ticket_sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('comment_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->boolean('customer_visible')->default(true);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('comment_sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('attachment_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->jsonb('mime_patterns')->nullable();
            $table->bigInteger('max_size_bytes')->default(10485760);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('relationship_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('article_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('workflow_entity_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('integration_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_types');
        Schema::dropIfExists('workflow_entity_types');
        Schema::dropIfExists('article_types');
        Schema::dropIfExists('relationship_types');
        Schema::dropIfExists('asset_statuses');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('attachment_types');
        Schema::dropIfExists('comment_sources');
        Schema::dropIfExists('comment_types');
        Schema::dropIfExists('ticket_sources');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_status_transitions');
        Schema::dropIfExists('ticket_statuses');
        Schema::dropIfExists('employment_statuses');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('locales');
    }
};
