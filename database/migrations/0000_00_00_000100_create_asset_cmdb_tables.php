<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('website', 500)->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();
        });

        Schema::create('asset_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->restrictOnDelete();
            $table->unsignedSmallInteger('asset_type_id');
            $table->string('model_name', 255);
            $table->string('part_number', 128)->nullable();
            $table->jsonb('attributes_schema')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('asset_type_id')->references('id')->on('asset_types')->restrictOnDelete();
            $table->unique(['organization_id', 'manufacturer_id', 'model_name']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('contract_ref', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('asset_tag', 128);
            $table->string('serial_number', 255)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->unsignedSmallInteger('asset_type_id');
            $table->unsignedSmallInteger('status_id');
            $table->foreignId('model_id')->nullable()->constrained('asset_models')->nullOnDelete();
            $table->foreignId('owner_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('custodian_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->timestampTz('installed_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->jsonb('ip_addresses')->nullable();
            $table->jsonb('mac_addresses')->nullable();
            $table->string('os_name', 255)->nullable();
            $table->string('os_version', 128)->nullable();
            $table->string('source_system', 32)->default('MANUAL');
            $table->string('external_id', 255)->nullable();
            $table->timestampTz('last_discovered_at')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('asset_type_id')->references('id')->on('asset_types')->restrictOnDelete();
            $table->foreign('status_id')->references('id')->on('asset_statuses')->restrictOnDelete();
            $table->unique(['organization_id', 'asset_tag']);
            $table->index(['asset_type_id', 'status_id']);
            $table->index(['owner_employee_id']);
            $table->index(['department_id', 'status_id']);
            $table->index(['hostname']);
        });

        Schema::create('ticket_assets', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('relation_type', 32)->default('AFFECTED');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->primary(['ticket_id', 'asset_id', 'relation_type']);
            $table->index(['asset_id', 'created_at']);
        });

        Schema::create('asset_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('target_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('relationship_type_id');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreign('relationship_type_id')->references('id')->on('relationship_types')->restrictOnDelete();
            $table->index(['target_asset_id', 'valid_to']);
        });

        Schema::create('asset_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('from_status_id')->nullable();
            $table->unsignedSmallInteger('to_status_id');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('from_status_id')->references('id')->on('asset_statuses')->nullOnDelete();
            $table->foreign('to_status_id')->references('id')->on('asset_statuses')->restrictOnDelete();
            $table->index(['asset_id', 'created_at']);
        });

        Schema::create('software_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('publisher', 255);
            $table->string('name', 255);
            $table->string('version_pattern', 255)->nullable();
            $table->boolean('is_licensed')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->unique(['organization_id', 'publisher', 'name']);
        });

        Schema::create('software_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('software_product_id')->constrained('software_products')->cascadeOnDelete();
            $table->string('version', 128)->nullable();
            $table->timestampTz('installed_at')->nullable();
            $table->timestampTz('discovered_at');
            $table->string('source_system', 32)->default('OCS');
            $table->string('external_id', 255)->nullable();

            $table->index(['software_product_id', 'discovered_at']);
        });

        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('software_product_id')->constrained('software_products')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->text('license_key_ciphertext')->nullable();
            $table->string('license_type', 32)->default('PERPETUAL');
            $table->integer('quantity')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('cost', 19, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();
        });

        Schema::create('license_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('software_licenses')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestampTz('allocated_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();

            $table->index(['license_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_allocations');
        Schema::dropIfExists('software_licenses');
        Schema::dropIfExists('software_installations');
        Schema::dropIfExists('software_products');
        Schema::dropIfExists('asset_status_history');
        Schema::dropIfExists('asset_relationships');
        Schema::dropIfExists('ticket_assets');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('asset_models');
        Schema::dropIfExists('manufacturers');
    }
};
