<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->boolean('republic_only')->default(false);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->string('bxm_code', 32)->nullable();
            $table->string('local_code', 32)->nullable();
        });
        Schema::create('office_support_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('bxm_code', 32);
            // Empty local code is the BXM-wide default, exact office wins.
            $table->string('local_code', 32)->default('');
            $table->string('name', 255);
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'bxm_code', 'local_code'], 'office_support_route_code_unique');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('bxm_code', 32)->nullable();
            $table->string('local_code', 32)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->string('support_scope', 16)->default('republic');
            $table->index(['organization_id', 'support_scope', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'support_scope', 'region_id']);
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['bxm_code', 'local_code', 'support_scope']);
        });
        Schema::dropIfExists('office_support_routes');
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['bxm_code', 'local_code']));
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('republic_only');
        });
    }
};
