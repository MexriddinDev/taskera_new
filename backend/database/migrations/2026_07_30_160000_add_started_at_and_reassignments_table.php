<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestampTz('started_at')->nullable()->after('first_response_at');
            $table->unsignedInteger('spent_minutes')->default(0)->after('resolved_at');
        });

        Schema::create('ticket_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reassigned_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->index(['ticket_id', 'created_at']);
            $table->index(['from_user_id', 'to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reassignments');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'spent_minutes']);
        });
    }
};
