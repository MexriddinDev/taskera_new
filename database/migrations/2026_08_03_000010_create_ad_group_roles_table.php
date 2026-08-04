<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_group_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('ad_group_name', 255);
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'ad_group_name', 'role_id']);
            $table->index(['ad_group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_roles');
    }
};
