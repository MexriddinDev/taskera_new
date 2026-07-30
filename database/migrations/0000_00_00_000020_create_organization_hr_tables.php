<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 32)->unique();
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->unsignedSmallInteger('default_locale_id')->nullable();
            $table->unsignedSmallInteger('default_timezone_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('default_locale_id')->references('id')->on('locales')->nullOnDelete();
            $table->foreign('default_timezone_id')->references('id')->on('timezones')->nullOnDelete();
            $table->index(['is_active']);
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('branch_type', 32)->default('HEADQUARTERS');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
            $table->index(['region_id', 'is_active']);
            $table->index(['parent_id']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->string('cost_center', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'branch_id', 'is_active']);
            $table->index(['parent_id']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('grade', 32)->nullable();
            $table->boolean('is_managerial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('employee_no', 64);
            $table->uuid('ad_object_guid')->nullable();
            $table->string('hr_external_id', 128)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('employment_status_id');
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->timestampTz('last_hr_sync_at')->nullable();
            $table->jsonb('attributes')->default('{}');
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('employment_status_id')->references('id')->on('employment_statuses')->restrictOnDelete();
            $table->unique(['organization_id', 'employee_no']);
            $table->index(['department_id', 'employment_status_id']);
            $table->index(['manager_id']);
            $table->index(['branch_id', 'employment_status_id']);
        });

        Schema::create('employee_sync_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->string('external_id', 128);
            $table->string('operation', 16);
            $table->char('payload_hash', 64);
            $table->jsonb('changed_fields')->nullable();
            $table->string('status', 16);
            $table->text('error_message')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['employee_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_sync_history');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('organizations');
    }
};
