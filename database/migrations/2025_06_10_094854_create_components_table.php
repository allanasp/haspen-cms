<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Components table defines reusable content block schemas.
     * Similar to Storyblok's component system for structured content.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id')->comment('Space this component belongs to');
            $table->uuid('uuid')->comment('Public UUID for API exposure');
            $table->string('name')->comment('Human-readable component name');
            $table->string('technical_name')->comment('Technical identifier for developers');
            $table->text('description')->nullable()->comment('Component description');
            
            // Component configuration
            $table->enum('type', ['content_type', 'nestable', 'universal'])->default('content_type')->comment('Component type');
            $table->jsonb('schema')->comment('Field definitions and validation rules');
            $table->jsonb('preview_field')->nullable()->comment('Field used for preview display');
            $table->string('preview_template')->nullable()->comment('Template for preview rendering');
            
            // Appearance and behavior
            $table->string('icon', 50)->nullable()->comment('Icon identifier for UI');
            $table->string('color', 7)->nullable()->comment('Hex color for UI theming');
            $table->jsonb('tabs')->nullable()->comment('Tab organization for fields');
            $table->boolean('is_root')->default(false)->comment('Can be used as page root component');
            $table->boolean('is_nestable')->default(true)->comment('Can be nested inside other components');
            
            // Restrictions and limits
            $table->jsonb('allowed_roles')->nullable()->comment('Roles that can use this component');
            $table->integer('max_instances')->nullable()->comment('Maximum instances allowed');
            
            // Versioning and status
            $table->integer('version')->default(1)->comment('Component schema version');
            $table->enum('status', ['draft', 'active', 'deprecated'])->default('draft')->comment('Component status');
            $table->unsignedBigInteger('created_by')->comment('User who created the component');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated the component');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('space_id')->references('id')->on('spaces')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraints
            $table->unique(['space_id', 'uuid'], 'components_space_uuid_unique');
            $table->unique(['space_id', 'technical_name'], 'components_space_technical_name_unique');
            
            // Indexes for performance
            $table->index(['space_id', 'status']);
            $table->index(['space_id', 'type']);
            $table->index(['is_root', 'status']);
            $table->index(['is_nestable', 'status']);
            $table->index('version');
            
            // GIN indexes for JSONB fields
            $table->rawIndex('USING GIN (schema)', 'components_schema_gin_idx');
            $table->rawIndex('USING GIN (preview_field)', 'components_preview_field_gin_idx');
            $table->rawIndex('USING GIN (tabs)', 'components_tabs_gin_idx');
            $table->rawIndex('USING GIN (allowed_roles)', 'components_allowed_roles_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
