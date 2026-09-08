<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('accept_minutes');
            $table->unsignedInteger('work_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_rules');
    }
};
