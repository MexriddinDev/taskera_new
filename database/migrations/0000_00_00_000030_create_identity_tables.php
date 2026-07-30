<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('username', 128);
            $table->string('email', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('auth_source', 32)->default('LOCAL');
            $table->string('status', 32)->default('ACTIVE');
            $table->unsignedSmallInteger('locale_id')->nullable();
            $table->unsignedSmallInteger('timezone_id')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->boolean('mfa_required')->default(true);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('locale_id')->references('id')->on('locales')->nullOnDelete();
            $table->foreign('timezone_id')->references('id')->on('timezones')->nullOnDelete();
            $table->unique(['organization_id', 'username']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_subject', 255);
            $table->string('username', 255)->nullable();
            $table->jsonb('claims')->nullable();
            $table->timestampTz('last_authenticated_at')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'provider', 'provider_subject']);
            $table->index(['user_id', 'provider']);
        });

        Schema::create('telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('username', 64);
            $table->string('token_secret_ref', 255);
            $table->char('webhook_secret_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'username']);
        });

        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_user_id', 32);
            $table->string('telegram_username', 64)->nullable();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('language_code', 10)->nullable();
            $table->string('private_chat_id', 32)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('blocked_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('verification_source', 32)->default('OTP');
            $table->jsonb('raw_profile')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'telegram_user_id']);
            $table->unique(['organization_id', 'user_id']);
            $table->index(['employee_id']);
            $table->index(['blocked_at']);
        });

        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->bigInteger('update_id');
            $table->foreignId('telegram_account_id')->nullable()->constrained('telegram_accounts')->nullOnDelete();
            $table->string('update_type', 32);
            $table->string('chat_id', 32)->nullable();
            $table->bigInteger('message_id')->nullable();
            $table->jsonb('payload');
            $table->char('payload_hash', 64);
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->smallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();

            $table->unique(['bot_id', 'update_id']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('telegram_accounts');
        Schema::dropIfExists('telegram_bots');
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('users');
    }
};
