<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('article_no', 32);
            $table->unsignedSmallInteger('article_type_id');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->text('content');
            $table->string('content_format', 16)->default('MARKDOWN');
            $table->string('status', 16)->default('DRAFT');
            $table->string('visibility', 16)->default('INTERNAL');
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('review_due_at')->nullable();
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('helpful_count')->default(0);
            $table->timestampsTz();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->softDeletesTz();
            $table->foreignId('deleted_by')->nullable();

            $table->foreign('article_type_id')->references('id')->on('article_types')->restrictOnDelete();
            $table->unique(['organization_id', 'article_no', 'version']);
            $table->index(['status', 'visibility', 'published_at']);
        });

        Schema::create('knowledge_article_links', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->string('linkable_type', 100);
            $table->unsignedBigInteger('linkable_id');
            $table->string('relation_type', 32)->default('RELATED');

            $table->primary(['article_id', 'linkable_type', 'linkable_id', 'relation_type']);
        });

        Schema::create('knowledge_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_feedback');
        Schema::dropIfExists('knowledge_article_links');
        Schema::dropIfExists('knowledge_articles');
    }
};
