<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Datasources table defines external data sources and their configuration.
     * Supports various data source types like JSON, CSV, API endpoints, etc.
     */
    public function up(): void
    {
        Schema::create('datasources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id')->comment('Space this datasource belongs to');
            $table->uuid('uuid')->comment('Public UUID for API exposure');
            $table->string('name')->comment('Datasource name');
            $table->string('slug')->comment('URL-friendly identifier');
            $table->text('description')->nullable()->comment('Datasource description');
            
            // Datasource configuration
            $table->enum('type', ['json', 'csv', 'api', 'database', 'custom'])->comment('Datasource type');
            $table->jsonb('config')->comment('Datasource-specific configuration');
            $table->jsonb('schema')->nullable()->comment('Expected data schema/structure');
            $table->jsonb('mapping')->nullable()->comment('Field mapping configuration');
            
            // Access and authentication
            $table->jsonb('auth_config')->nullable()->comment('Authentication configuration for external sources');
            $table->jsonb('headers')->nullable()->comment('Custom headers for API requests');
            $table->string('cache_key')->nullable()->comment('Cache key for data caching');
            $table->integer('cache_duration')->default(3600)->comment('Cache duration in seconds');
            
            // Data management
            $table->boolean('auto_sync')->default(false)->comment('Whether to automatically sync data');
            $table->string('sync_frequency')->nullable()->comment('Sync frequency (cron expression)');
            $table->timestamp('last_synced_at')->nullable()->comment('Last successful sync timestamp');
            $table->jsonb('sync_status')->nullable()->comment('Last sync status and errors');
            
            // Filtering and processing
            $table->jsonb('filters')->nullable()->comment('Data filtering rules');
            $table->jsonb('transformations')->nullable()->comment('Data transformation rules');
            $table->integer('max_entries')->nullable()->comment('Maximum number of entries to fetch');
            
            // Status and monitoring
            $table->enum('status', ['active', 'inactive', 'error'])->default('active')->comment('Datasource status');
            $table->jsonb('health_check')->nullable()->comment('Health check configuration');
            $table->timestamp('last_health_check_at')->nullable()->comment('Last health check timestamp');
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->comment('User who created the datasource');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated the datasource');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints
            $table->foreign('space_id')->references('id')->on('spaces')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraints
            $table->unique(['space_id', 'uuid'], 'datasources_space_uuid_unique');
            $table->unique(['space_id', 'slug'], 'datasources_space_slug_unique');
            
            // Indexes for performance
            $table->index(['space_id', 'status']);
            $table->index(['space_id', 'type']);
            $table->index(['auto_sync', 'status']);
            $table->index(['last_synced_at']);
            $table->index(['last_health_check_at']);
            
            // GIN indexes for JSONB fields
            $table->rawIndex('USING GIN (config)', 'datasources_config_gin_idx');
            $table->rawIndex('USING GIN (schema)', 'datasources_schema_gin_idx');
            $table->rawIndex('USING GIN (mapping)', 'datasources_mapping_gin_idx');
            $table->rawIndex('USING GIN (auth_config)', 'datasources_auth_config_gin_idx');
            $table->rawIndex('USING GIN (filters)', 'datasources_filters_gin_idx');
            $table->rawIndex('USING GIN (transformations)', 'datasources_transformations_gin_idx');
            $table->rawIndex('USING GIN (sync_status)', 'datasources_sync_status_gin_idx');
            $table->rawIndex('USING GIN (health_check)', 'datasources_health_check_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datasources');
    }
};
