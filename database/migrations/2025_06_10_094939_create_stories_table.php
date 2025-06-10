<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Stories table contains the actual content pages/entries.
     * Each story is composed of components and their content data.
     */
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id')->comment('Space this story belongs to');
            $table->uuid('uuid')->comment('Public UUID for API exposure');
            $table->string('name')->comment('Story name/title');
            $table->string('slug')->comment('URL-friendly identifier');
            $table->text('description')->nullable()->comment('Story description');
            
            // Content structure
            $table->unsignedBigInteger('component_id')->nullable()->comment('Root component type');
            $table->jsonb('content')->comment('Story content data structured by components');
            $table->jsonb('meta_data')->default('{}')->comment('SEO and meta information');
            
            // Hierarchy and organization
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Parent story for hierarchical content');
            $table->string('path')->comment('Full path for nested stories');
            $table->integer('sort_order')->default(0)->comment('Sort order within parent');
            $table->jsonb('breadcrumbs')->nullable()->comment('Cached breadcrumb data');
            
            // Publishing and workflow
            $table->enum('status', ['draft', 'in_review', 'published', 'archived'])->default('draft')->comment('Publishing status');
            $table->boolean('is_folder')->default(false)->comment('Whether story is a folder/category');
            $table->boolean('is_startpage')->default(false)->comment('Whether story is space homepage');
            $table->timestamp('published_at')->nullable()->comment('Publication timestamp');
            $table->timestamp('scheduled_at')->nullable()->comment('Scheduled publication time');
            $table->timestamp('expires_at')->nullable()->comment('Content expiration time');
            
            // Localization
            $table->string('language', 10)->default('en')->comment('Story language');
            $table->unsignedBigInteger('translation_group_id')->nullable()->comment('Group ID for translations');
            $table->jsonb('translated_languages')->default('[]')->comment('Available translations');
            
            // Access control and visibility
            $table->boolean('is_published')->default(false)->comment('Whether story is publicly visible');
            $table->boolean('is_password_protected')->default(false)->comment('Whether story requires password');
            $table->string('password')->nullable()->comment('Access password (hashed)');
            $table->jsonb('allowed_roles')->nullable()->comment('Roles that can access this story');
            
            // SEO and external
            $table->string('external_id')->nullable()->comment('External system identifier');
            $table->string('canonical_url')->nullable()->comment('Canonical URL for SEO');
            $table->jsonb('robots_meta')->nullable()->comment('Robots meta directives');
            
            // Audit and versioning
            $table->unsignedBigInteger('created_by')->comment('User who created the story');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated the story');
            $table->integer('version')->default(1)->comment('Content version number');
            $table->timestamp('content_updated_at')->nullable()->comment('Last content modification time');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('space_id')->references('id')->on('spaces')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('stories')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraints
            $table->unique(['space_id', 'uuid'], 'stories_space_uuid_unique');
            $table->unique(['space_id', 'path'], 'stories_space_path_unique');
            $table->unique(['space_id', 'language', 'slug'], 'stories_space_lang_slug_unique');
            
            // Indexes for performance
            $table->index(['space_id', 'status']);
            $table->index(['space_id', 'language']);
            $table->index(['space_id', 'is_published']);
            $table->index(['space_id', 'parent_id']);
            $table->index(['space_id', 'is_folder']);
            $table->index(['space_id', 'is_startpage']);
            $table->index(['translation_group_id']);
            $table->index(['published_at']);
            $table->index(['scheduled_at']);
            $table->index(['expires_at']);
            $table->index(['path']);
            $table->index(['sort_order']);
            
            // GIN indexes for JSONB fields
            $table->rawIndex('USING GIN (content)', 'stories_content_gin_idx');
            $table->rawIndex('USING GIN (meta_data)', 'stories_meta_data_gin_idx');
            $table->rawIndex('USING GIN (breadcrumbs)', 'stories_breadcrumbs_gin_idx');
            $table->rawIndex('USING GIN (translated_languages)', 'stories_translated_languages_gin_idx');
            $table->rawIndex('USING GIN (allowed_roles)', 'stories_allowed_roles_gin_idx');
            $table->rawIndex('USING GIN (robots_meta)', 'stories_robots_meta_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
