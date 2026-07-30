<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('commentable_type', 100);
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('type_id');
            $table->unsignedSmallInteger('source_id');
            $table->text('body');
            $table->string('body_format', 16)->default('MARKDOWN');
            $table->string('external_message_id', 128)->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('type_id')->references('id')->on('comment_types')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('comment_sources')->restrictOnDelete();
            $table->index(['commentable_type', 'commentable_id', 'created_at']);
            $table->index(['author_user_id', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('attachable_type', 100);
            $table->unsignedBigInteger('attachable_id');
            $table->unsignedSmallInteger('attachment_type_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('source_id');
            $table->string('storage_disk', 32)->default('s3');
            $table->string('storage_path', 1024);
            $table->string('original_name', 500);
            $table->string('safe_name', 500);
            $table->string('mime_type', 255);
            $table->string('extension', 32)->nullable();
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('telegram_file_id', 255)->nullable();
            $table->string('telegram_file_unique_id', 255)->nullable();
            $table->string('antivirus_status', 16)->default('PENDING');
            $table->jsonb('scan_result')->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_key_ref', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('attachment_type_id')->references('id')->on('attachment_types')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('ticket_sources')->restrictOnDelete();
            $table->index(['attachable_type', 'attachable_id', 'created_at']);
            $table->index(['sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
    }
};
