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
        // Create a function to get current user's accessible space IDs
        DB::statement("
            CREATE OR REPLACE FUNCTION get_current_user_space_ids()
            RETURNS INTEGER[] AS $$
            DECLARE
                user_id INTEGER;
                space_ids INTEGER[];
            BEGIN
                -- Get current user ID from session or application context
                -- This would be set by your application when authenticating users
                user_id := COALESCE(current_setting('app.current_user_id', true)::INTEGER, 0);
                
                IF user_id = 0 THEN
                    RETURN ARRAY[]::INTEGER[];
                END IF;
                
                -- Get all space IDs this user has access to
                SELECT ARRAY_AGG(DISTINCT space_id) INTO space_ids
                FROM space_user 
                WHERE user_id = get_current_user_space_ids.user_id;
                
                RETURN COALESCE(space_ids, ARRAY[]::INTEGER[]);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        // Create a function to check if user is admin
        DB::statement("
            CREATE OR REPLACE FUNCTION is_current_user_admin()
            RETURNS BOOLEAN AS $$
            DECLARE
                user_id INTEGER;
                is_admin BOOLEAN;
            BEGIN
                user_id := COALESCE(current_setting('app.current_user_id', true)::INTEGER, 0);
                
                IF user_id = 0 THEN
                    RETURN FALSE;
                END IF;
                
                SELECT u.is_admin INTO is_admin
                FROM users u 
                WHERE u.id = is_current_user_admin.user_id;
                
                RETURN COALESCE(is_admin, FALSE);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        // Enable RLS on all tenant-scoped tables
        $tenantTables = [
            'stories',
            'components', 
            'assets',
            'datasources',
            'datasource_entries',
            'story_versions'
        ];

        foreach ($tenantTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        }

        // Create RLS policies for each tenant-scoped table
        
        // Stories table policies
        DB::statement("
            CREATE POLICY stories_tenant_isolation ON stories
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        // Components table policies
        DB::statement("
            CREATE POLICY components_tenant_isolation ON components
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        // Assets table policies
        DB::statement("
            CREATE POLICY assets_tenant_isolation ON assets
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        // Datasources table policies
        DB::statement("
            CREATE POLICY datasources_tenant_isolation ON datasources
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        // Datasource entries table policies (inherits from datasource space_id)
        DB::statement("
            CREATE POLICY datasource_entries_tenant_isolation ON datasource_entries
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                EXISTS (
                    SELECT 1 FROM datasources d 
                    WHERE d.id = datasource_entries.datasource_id 
                    AND d.space_id = ANY(get_current_user_space_ids())
                )
            )
        ");

        // Story versions table policies (inherits from story space_id)
        DB::statement("
            CREATE POLICY story_versions_tenant_isolation ON story_versions
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                EXISTS (
                    SELECT 1 FROM stories s 
                    WHERE s.id = story_versions.story_id 
                    AND s.space_id = ANY(get_current_user_space_ids())
                )
            )
        ");

        // Special policy for spaces table - users can only see spaces they belong to
        DB::statement("ALTER TABLE spaces ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY spaces_user_access ON spaces
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                id = ANY(get_current_user_space_ids())
            )
        ");

        // Users table - users can see themselves and users in their spaces
        DB::statement("ALTER TABLE users ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY users_tenant_access ON users
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                id = COALESCE(current_setting('app.current_user_id', true)::INTEGER, 0) OR
                EXISTS (
                    SELECT 1 FROM space_user su1
                    WHERE su1.user_id = users.id
                    AND su1.space_id = ANY(get_current_user_space_ids())
                )
            )
        ");

        // Space_user table - users can see their own relationships and relationships in their spaces
        DB::statement("ALTER TABLE space_user ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY space_user_access ON space_user
            FOR ALL TO public
            USING (
                is_current_user_admin() OR 
                user_id = COALESCE(current_setting('app.current_user_id', true)::INTEGER, 0) OR
                space_id = ANY(get_current_user_space_ids())
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all RLS policies
        $policies = [
            'stories_tenant_isolation',
            'components_tenant_isolation', 
            'assets_tenant_isolation',
            'datasources_tenant_isolation',
            'datasource_entries_tenant_isolation',
            'story_versions_tenant_isolation',
            'spaces_user_access',
            'users_tenant_access',
            'space_user_access'
        ];

        foreach ($policies as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON " . substr($policy, 0, strpos($policy, '_')));
        }

        // Disable RLS on all tables
        $tables = [
            'stories', 'components', 'assets', 'datasources', 
            'datasource_entries', 'story_versions', 'spaces', 'users', 'space_user'
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        // Drop helper functions
        DB::statement("DROP FUNCTION IF EXISTS get_current_user_space_ids()");
        DB::statement("DROP FUNCTION IF EXISTS is_current_user_admin()");
    }
};