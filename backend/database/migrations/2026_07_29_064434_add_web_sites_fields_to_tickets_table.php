<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('floor', 128)->nullable()->after('location_id');
            $table->string('target_department', 32)->nullable()->after('floor');
            $table->string('origin_department', 128)->nullable()->after('target_department');
            $table->string('initiator_name', 128)->nullable()->after('origin_department');
            $table->string('initiator_phone', 32)->nullable()->after('initiator_name');
            $table->string('device_name', 255)->nullable()->after('initiator_phone');
            $table->string('broken_url', 2048)->nullable()->after('device_name');
            $table->text('rejection_reason')->nullable()->after('resolution_summary');
            $table->text('solution_comment')->nullable()->after('rejection_reason');
            $table->unsignedTinyInteger('client_rating')->nullable()->after('solution_comment');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'floor', 'target_department', 'origin_department',
                'initiator_name', 'initiator_phone', 'device_name',
                'broken_url', 'rejection_reason', 'solution_comment',
                'client_rating',
            ]);
        });
    }
};
