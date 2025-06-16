<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add search vector columns to searchable tables
        Schema::table('stories', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable()->comment('Full-text search vector for story content');
        });

        Schema::table('components', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable()->comment('Full-text search vector for component schema and metadata');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable()->comment('Full-text search vector for asset metadata');
        });

        Schema::table('datasources', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable()->comment('Full-text search vector for datasource configuration');
        });

        Schema::table('datasource_entries', function (Blueprint $table) {
            $table->tsvector('search_vector')->nullable()->comment('Full-text search vector for datasource entry data');
        });

        // Create GIN indexes for full-text search
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stories_search_vector_idx ON stories USING GIN (search_vector)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS components_search_vector_idx ON components USING GIN (search_vector)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS assets_search_vector_idx ON assets USING GIN (search_vector)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasources_search_vector_idx ON datasources USING GIN (search_vector)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS datasource_entries_search_vector_idx ON datasource_entries USING GIN (search_vector)');

        // Create functions to update search vectors
        
        // Stories search vector update function
        DB::statement("
            CREATE OR REPLACE FUNCTION update_stories_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector := 
                    setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.slug, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.meta_title, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.meta_description, '')), 'C') ||
                    setweight(to_tsvector('english', COALESCE(NEW.content::text, '')), 'D');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Components search vector update function  
        DB::statement("
            CREATE OR REPLACE FUNCTION update_components_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector := 
                    setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.slug, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'C') ||
                    setweight(to_tsvector('english', COALESCE(NEW.schema::text, '')), 'D');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Assets search vector update function
        DB::statement("
            CREATE OR REPLACE FUNCTION update_assets_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector := 
                    setweight(to_tsvector('english', COALESCE(NEW.filename, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.alt_text, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'C') ||
                    setweight(to_tsvector('english', COALESCE(NEW.metadata::text, '')), 'D');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Datasources search vector update function
        DB::statement("
            CREATE OR REPLACE FUNCTION update_datasources_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector := 
                    setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.slug, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'C') ||
                    setweight(to_tsvector('english', COALESCE(NEW.config::text, '')), 'D');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Datasource entries search vector update function
        DB::statement("
            CREATE OR REPLACE FUNCTION update_datasource_entries_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector := 
                    setweight(to_tsvector('english', COALESCE(NEW.external_id, '')), 'B') ||
                    setweight(to_tsvector('english', COALESCE(NEW.data::text, '')), 'C') ||
                    setweight(to_tsvector('english', COALESCE(NEW.metadata::text, '')), 'D');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create triggers to automatically update search vectors
        DB::statement('
            CREATE TRIGGER stories_search_vector_update
            BEFORE INSERT OR UPDATE ON stories
            FOR EACH ROW EXECUTE FUNCTION update_stories_search_vector()
        ');

        DB::statement('
            CREATE TRIGGER components_search_vector_update
            BEFORE INSERT OR UPDATE ON components
            FOR EACH ROW EXECUTE FUNCTION update_components_search_vector()
        ');

        DB::statement('
            CREATE TRIGGER assets_search_vector_update
            BEFORE INSERT OR UPDATE ON assets
            FOR EACH ROW EXECUTE FUNCTION update_assets_search_vector()
        ');

        DB::statement('
            CREATE TRIGGER datasources_search_vector_update
            BEFORE INSERT OR UPDATE ON datasources
            FOR EACH ROW EXECUTE FUNCTION update_datasources_search_vector()
        ');

        DB::statement('
            CREATE TRIGGER datasource_entries_search_vector_update
            BEFORE INSERT OR UPDATE ON datasource_entries
            FOR EACH ROW EXECUTE FUNCTION update_datasource_entries_search_vector()
        ');

        // Update existing records with search vectors
        DB::statement('UPDATE stories SET search_vector = 
            setweight(to_tsvector(\'english\', COALESCE(name, \'\')), \'A\') ||
            setweight(to_tsvector(\'english\', COALESCE(slug, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(meta_title, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(meta_description, \'\')), \'C\') ||
            setweight(to_tsvector(\'english\', COALESCE(content::text, \'\')), \'D\')
        ');

        DB::statement('UPDATE components SET search_vector = 
            setweight(to_tsvector(\'english\', COALESCE(name, \'\')), \'A\') ||
            setweight(to_tsvector(\'english\', COALESCE(slug, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(description, \'\')), \'C\') ||
            setweight(to_tsvector(\'english\', COALESCE(schema::text, \'\')), \'D\')
        ');

        DB::statement('UPDATE assets SET search_vector = 
            setweight(to_tsvector(\'english\', COALESCE(filename, \'\')), \'A\') ||
            setweight(to_tsvector(\'english\', COALESCE(name, \'\')), \'A\') ||
            setweight(to_tsvector(\'english\', COALESCE(alt_text, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(description, \'\')), \'C\') ||
            setweight(to_tsvector(\'english\', COALESCE(metadata::text, \'\')), \'D\')
        ');

        DB::statement('UPDATE datasources SET search_vector = 
            setweight(to_tsvector(\'english\', COALESCE(name, \'\')), \'A\') ||
            setweight(to_tsvector(\'english\', COALESCE(slug, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(description, \'\')), \'C\') ||
            setweight(to_tsvector(\'english\', COALESCE(config::text, \'\')), \'D\')
        ');

        DB::statement('UPDATE datasource_entries SET search_vector = 
            setweight(to_tsvector(\'english\', COALESCE(external_id, \'\')), \'B\') ||
            setweight(to_tsvector(\'english\', COALESCE(data::text, \'\')), \'C\') ||
            setweight(to_tsvector(\'english\', COALESCE(metadata::text, \'\')), \'D\')
        ');

        // Create a materialized view for global search across all content types
        DB::statement("
            CREATE MATERIALIZED VIEW global_search_index AS
            SELECT 
                'story' as content_type,
                id,
                space_id,
                name as title,
                slug,
                search_vector,
                status,
                created_at,
                updated_at
            FROM stories
            WHERE deleted_at IS NULL
            UNION ALL
            SELECT 
                'component' as content_type,
                id,
                space_id,
                name as title,
                slug,
                search_vector,
                status,
                created_at,
                updated_at
            FROM components
            WHERE deleted_at IS NULL
            UNION ALL
            SELECT 
                'asset' as content_type,
                id,
                space_id,
                COALESCE(name, filename) as title,
                NULL as slug,
                search_vector,
                'published' as status,
                created_at,
                updated_at
            FROM assets
            WHERE deleted_at IS NULL
            UNION ALL
            SELECT 
                'datasource' as content_type,
                id,
                space_id,
                name as title,
                slug,
                search_vector,
                status,
                created_at,
                updated_at
            FROM datasources
            WHERE deleted_at IS NULL
        ");

        // Create index on the materialized view
        DB::statement('CREATE INDEX global_search_index_search_vector_idx ON global_search_index USING GIN (search_vector)');
        DB::statement('CREATE INDEX global_search_index_space_id_idx ON global_search_index (space_id)');
        DB::statement('CREATE INDEX global_search_index_content_type_idx ON global_search_index (content_type)');
        DB::statement('CREATE INDEX global_search_index_status_idx ON global_search_index (status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop materialized view
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS global_search_index');

        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS stories_search_vector_update ON stories');
        DB::statement('DROP TRIGGER IF EXISTS components_search_vector_update ON components');
        DB::statement('DROP TRIGGER IF EXISTS assets_search_vector_update ON assets');
        DB::statement('DROP TRIGGER IF EXISTS datasources_search_vector_update ON datasources');
        DB::statement('DROP TRIGGER IF EXISTS datasource_entries_search_vector_update ON datasource_entries');

        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS update_stories_search_vector()');
        DB::statement('DROP FUNCTION IF EXISTS update_components_search_vector()');
        DB::statement('DROP FUNCTION IF EXISTS update_assets_search_vector()');
        DB::statement('DROP FUNCTION IF EXISTS update_datasources_search_vector()');
        DB::statement('DROP FUNCTION IF EXISTS update_datasource_entries_search_vector()');

        // Drop indexes
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stories_search_vector_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS components_search_vector_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS assets_search_vector_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasources_search_vector_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS datasource_entries_search_vector_idx');

        // Drop search vector columns
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        Schema::table('datasources', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });

        Schema::table('datasource_entries', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });
    }
};