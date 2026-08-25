<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('requester_email', 255)->nullable()->after('initiator_phone');
            $table->string('requester_position', 255)->nullable()->after('requester_email');
            $table->string('requester_username', 128)->nullable()->after('requester_position');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['requester_email', 'requester_position', 'requester_username']);
        });
    }
};
