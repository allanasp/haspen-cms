<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable GIN indexes for JSONB fields across all tables
        // These were previously commented out but are essential for PostgreSQL performance
        
        // Spaces table - settings and metadata JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS spaces_settings_gin_idx ON spaces USING GIN (settings)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS spaces_metadata_gin_idx ON spaces USING GIN (metadata)');
        
        // Stories table - content and metadata JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stories_content_gin_idx ON stories USING GIN (content)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stories_metadata_gin_idx ON stories USING GIN (metadata)');
        
        // Components table - schema and config JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS components_schema_gin_idx ON components USING GIN (schema)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS components_config_gin_idx ON components USING GIN (config)');
        
        // Assets table - metadata and processing_results JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assets_metadata_gin_idx ON assets USING GIN (metadata)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assets_processing_results_gin_idx ON assets USING GIN (processing_results)');
        
        // Datasources table - config, schema, mapping, and other JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_config_gin_idx ON datasources USING GIN (config)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_schema_gin_idx ON datasources USING GIN (schema)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_mapping_gin_idx ON datasources USING GIN (mapping)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_auth_config_gin_idx ON datasources USING GIN (auth_config)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_headers_gin_idx ON datasources USING GIN (headers)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_filters_gin_idx ON datasources USING GIN (filters)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_transformations_gin_idx ON datasources USING GIN (transformations)');
        
        // Datasource entries table - data and metadata JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasource_entries_data_gin_idx ON datasource_entries USING GIN (data)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasource_entries_metadata_gin_idx ON datasource_entries USING GIN (metadata)');
        
        // Users table - preferences and metadata JSONB fields
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS users_preferences_gin_idx ON users USING GIN (preferences)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS users_metadata_gin_idx ON users USING GIN (metadata)');
        
        // Roles table - permissions JSONB field
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS roles_permissions_gin_idx ON roles USING GIN (permissions)');
        
        // Story versions table - content JSONB field
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS story_versions_content_gin_idx ON story_versions USING GIN (content)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all GIN indexes created in up() method
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS spaces_settings_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS spaces_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stories_content_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stories_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS components_schema_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS components_config_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assets_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assets_processing_results_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_config_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_schema_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_mapping_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_auth_config_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_headers_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_filters_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_transformations_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasource_entries_data_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasource_entries_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_preferences_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS roles_permissions_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS story_versions_content_gin_idx');
    }
};