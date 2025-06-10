<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Datasource entries table stores the actual data fetched from datasources.
     * Supports multi-dimensional data with flexible JSON storage.
     */
    public function up(): void
    {
        Schema::create('datasource_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('datasource_id')->comment('Reference to datasource');
            $table->uuid('uuid')->comment('Public UUID for API exposure');
            $table->string('external_id')->nullable()->comment('Identifier from external source');
            
            // Entry data and structure
            $table->string('name')->comment('Entry name/title');
            $table->string('slug')->nullable()->comment('URL-friendly identifier');
            $table->jsonb('data')->comment('Entry data from datasource');
            $table->jsonb('raw_data')->nullable()->comment('Original unprocessed data');
            $table->jsonb('computed_fields')->nullable()->comment('Computed/derived fields');
            
            // Categorization and hierarchy
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Parent entry for hierarchical data');
            $table->string('path')->nullable()->comment('Hierarchical path');
            $table->integer('sort_order')->default(0)->comment('Sort order');
            $table->jsonb('dimensions')->nullable()->comment('Multi-dimensional categorization');
            
            // Status and processing
            $table->enum('status', ['active', 'inactive', 'error'])->default('active')->comment('Entry status');
            $table->boolean('is_processed')->default(false)->comment('Whether entry has been processed');
            $table->timestamp('processed_at')->nullable()->comment('Processing timestamp');
            $table->jsonb('processing_errors')->nullable()->comment('Processing error details');
            
            // Caching and performance
            $table->string('checksum', 64)->nullable()->comment('Data checksum for change detection');
            $table->timestamp('data_updated_at')->nullable()->comment('When data was last updated');
            $table->timestamp('last_fetched_at')->nullable()->comment('When data was last fetched');
            
            // Analytics and usage
            $table->integer('access_count')->default(0)->comment('Number of times accessed');
            $table->timestamp('last_accessed_at')->nullable()->comment('Last access timestamp');
            $table->jsonb('usage_stats')->default('{}')->comment('Detailed usage statistics');
            
            // Localization
            $table->string('language', 10)->nullable()->comment('Entry language');
            $table->unsignedBigInteger('translation_group_id')->nullable()->comment('Translation group ID');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('datasource_id')->references('id')->on('datasources')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('datasource_entries')->onDelete('cascade');
            
            // Unique constraints
            $table->unique(['datasource_id', 'uuid'], 'datasource_entries_datasource_uuid_unique');
            $table->unique(['datasource_id', 'external_id'], 'datasource_entries_datasource_external_id_unique');
            $table->unique(['datasource_id', 'slug'], 'datasource_entries_datasource_slug_unique');
            
            // Indexes for performance
            $table->index(['datasource_id', 'status']);
            $table->index(['datasource_id', 'parent_id']);
            $table->index(['datasource_id', 'language']);
            $table->index(['datasource_id', 'sort_order']);
            $table->index(['translation_group_id']);
            $table->index(['is_processed']);
            $table->index(['checksum']);
            $table->index(['data_updated_at']);
            $table->index(['last_accessed_at']);
            $table->index(['access_count']);
            
            // GIN indexes for JSONB fields
            $table->rawIndex('USING GIN (data)', 'datasource_entries_data_gin_idx');
            $table->rawIndex('USING GIN (raw_data)', 'datasource_entries_raw_data_gin_idx');
            $table->rawIndex('USING GIN (computed_fields)', 'datasource_entries_computed_fields_gin_idx');
            $table->rawIndex('USING GIN (dimensions)', 'datasource_entries_dimensions_gin_idx');
            $table->rawIndex('USING GIN (processing_errors)', 'datasource_entries_processing_errors_gin_idx');
            $table->rawIndex('USING GIN (usage_stats)', 'datasource_entries_usage_stats_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasource_entries');
    }
};
