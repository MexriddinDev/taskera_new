<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('username', 64)->index();
            $table->string('email', 255)->index();
            $table->text('password_encrypted');
            $table->string('bxm_code', 20)->nullable();
            $table->string('ou_dn', 512)->nullable();
            $table->string('group_dn', 512)->nullable();
            $table->uuid('ad_object_guid')->nullable();
            $table->string('status', 20)->default('CREATED'); // CREATED | FAILED
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};