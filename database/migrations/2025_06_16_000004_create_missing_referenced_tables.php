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
        // Create asset_folders table
        Schema::create('asset_folders', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->uuid('uuid')->unique()->comment('UUID for public identification');
            $table->foreignId('space_id')->constrained('spaces')->onDelete('cascade')->comment('Space this folder belongs to');
            $table->string('name')->comment('Folder name');
            $table->string('slug')->comment('URL-friendly folder identifier');
            $table->text('description')->nullable()->comment('Folder description');
            $table->string('color', 7)->nullable()->comment('Folder color (hex code)');
            $table->foreignId('parent_id')->nullable()->constrained('asset_folders')->onDelete('cascade')->comment('Parent folder for nesting');
            $table->json('metadata')->nullable()->comment('Additional folder metadata');
            $table->integer('sort_order')->default(0)->comment('Sort order within parent folder');
            $table->integer('assets_count')->default(0)->comment('Cached count of assets in folder');
            $table->foreignId('created_by')->constrained('users')->comment('User who created the folder');
            $table->foreignId('updated_by')->nullable()->constrained('users')->comment('User who last updated the folder');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['space_id', 'parent_id'], 'asset_folders_space_parent_idx');
            $table->index(['space_id', 'slug'], 'asset_folders_space_slug_idx');
            $table->index(['space_id', 'sort_order'], 'asset_folders_space_sort_idx');
            $table->index('created_by', 'asset_folders_created_by_idx');
            
            // Unique constraints
            $table->unique(['space_id', 'slug'], 'asset_folders_space_slug_unique');
            $table->unique(['space_id', 'parent_id', 'name'], 'asset_folders_space_parent_name_unique');
        });

        // Create translation_groups table
        Schema::create('translation_groups', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->uuid('uuid')->unique()->comment('UUID for public identification');
            $table->foreignId('space_id')->constrained('spaces')->onDelete('cascade')->comment('Space this group belongs to');
            $table->string('name')->comment('Human-readable name for the translation group');
            $table->string('slug')->comment('URL-friendly group identifier');
            $table->text('description')->nullable()->comment('Description of the translation group');
            $table->string('default_language', 10)->comment('Default language code for this group');
            $table->json('available_languages')->comment('Array of available language codes');
            $table->json('metadata')->nullable()->comment('Additional group metadata');
            $table->boolean('is_complete')->default(false)->comment('Whether all languages have translations');
            $table->timestamp('last_translation_at')->nullable()->comment('When the last translation was added');
            $table->foreignId('created_by')->constrained('users')->comment('User who created the group');
            $table->foreignId('updated_by')->nullable()->constrained('users')->comment('User who last updated the group');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['space_id', 'default_language'], 'translation_groups_space_lang_idx');
            $table->index(['space_id', 'slug'], 'translation_groups_space_slug_idx');
            $table->index(['space_id', 'is_complete'], 'translation_groups_space_complete_idx');
            $table->index('last_translation_at', 'translation_groups_last_translation_idx');
            
            // Unique constraints
            $table->unique(['space_id', 'slug'], 'translation_groups_space_slug_unique');
        });

        // Create workflow_states table for content approval processes
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->uuid('uuid')->unique()->comment('UUID for public identification');
            $table->foreignId('space_id')->constrained('spaces')->onDelete('cascade')->comment('Space this workflow belongs to');
            $table->string('name')->comment('Workflow state name');
            $table->string('slug')->comment('URL-friendly state identifier');
            $table->text('description')->nullable()->comment('Workflow state description');
            $table->string('color', 7)->default('#6B7280')->comment('State color (hex code)');
            $table->integer('sort_order')->default(0)->comment('Sort order of workflow states');
            $table->boolean('is_initial')->default(false)->comment('Whether this is an initial state');
            $table->boolean('is_final')->default(false)->comment('Whether this is a final state');
            $table->boolean('is_published')->default(false)->comment('Whether content in this state is published');
            $table->json('permissions')->nullable()->comment('Required permissions to transition to this state');
            $table->json('allowed_transitions')->nullable()->comment('Array of state IDs this state can transition to');
            $table->json('metadata')->nullable()->comment('Additional state metadata');
            $table->foreignId('created_by')->constrained('users')->comment('User who created the state');
            $table->foreignId('updated_by')->nullable()->constrained('users')->comment('User who last updated the state');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['space_id', 'sort_order'], 'workflow_states_space_sort_idx');
            $table->index(['space_id', 'is_initial'], 'workflow_states_space_initial_idx');
            $table->index(['space_id', 'is_final'], 'workflow_states_space_final_idx');
            $table->index(['space_id', 'is_published'], 'workflow_states_space_published_idx');
            
            // Unique constraints
            $table->unique(['space_id', 'slug'], 'workflow_states_space_slug_unique');
        });

        // Create story_workflow_transitions table for tracking content workflow changes
        Schema::create('story_workflow_transitions', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->uuid('uuid')->unique()->comment('UUID for public identification');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade')->comment('Story that transitioned');
            $table->foreignId('from_state_id')->nullable()->constrained('workflow_states')->comment('Previous workflow state');
            $table->foreignId('to_state_id')->constrained('workflow_states')->comment('New workflow state');
            $table->text('comment')->nullable()->comment('Optional comment about the transition');
            $table->json('metadata')->nullable()->comment('Additional transition metadata');
            $table->foreignId('transitioned_by')->constrained('users')->comment('User who performed the transition');
            $table->timestamp('transitioned_at')->useCurrent()->comment('When the transition occurred');
            $table->timestamps();

            // Indexes
            $table->index(['story_id', 'transitioned_at'], 'story_transitions_story_date_idx');
            $table->index(['to_state_id', 'transitioned_at'], 'story_transitions_state_date_idx');
            $table->index('transitioned_by', 'story_transitions_user_idx');
        });

        // Add GIN indexes for JSON columns
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS asset_folders_metadata_gin_idx ON asset_folders USING GIN (metadata)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS translation_groups_metadata_gin_idx ON translation_groups USING GIN (metadata)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS translation_groups_languages_gin_idx ON translation_groups USING GIN (available_languages)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS workflow_states_metadata_gin_idx ON workflow_states USING GIN (metadata)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS workflow_states_permissions_gin_idx ON workflow_states USING GIN (permissions)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS workflow_states_transitions_gin_idx ON workflow_states USING GIN (allowed_transitions)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS story_transitions_metadata_gin_idx ON story_workflow_transitions USING GIN (metadata)');

        // Update assets table to add the foreign key constraint for folder_id
        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('folder_id')->references('id')->on('asset_folders')->onDelete('set null');
        });

        // Update stories table to add the foreign key constraint for translation_group_id
        Schema::table('stories', function (Blueprint $table) {
            $table->foreign('translation_group_id')->references('id')->on('translation_groups')->onDelete('set null');
        });

        // Add workflow state to stories table
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('workflow_state_id')->nullable()->after('status')->constrained('workflow_states')->onDelete('set null')->comment('Current workflow state');
            $table->index(['space_id', 'workflow_state_id'], 'stories_space_workflow_idx');
        });

        // Enable RLS on new tables
        DB::statement('ALTER TABLE asset_folders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE translation_groups ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE workflow_states ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE story_workflow_transitions ENABLE ROW LEVEL SECURITY');

        // Create RLS policies for new tables
        DB::statement("
            CREATE POLICY asset_folders_tenant_isolation ON asset_folders
            FOR ALL TO public
            USING (
                (current_setting('app.bypass_rls', true)::boolean = true) OR
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        DB::statement("
            CREATE POLICY translation_groups_tenant_isolation ON translation_groups
            FOR ALL TO public
            USING (
                (current_setting('app.bypass_rls', true)::boolean = true) OR
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        DB::statement("
            CREATE POLICY workflow_states_tenant_isolation ON workflow_states
            FOR ALL TO public
            USING (
                (current_setting('app.bypass_rls', true)::boolean = true) OR
                space_id = ANY(get_current_user_space_ids())
            )
        ");

        DB::statement("
            CREATE POLICY story_workflow_transitions_tenant_isolation ON story_workflow_transitions
            FOR ALL TO public
            USING (
                (current_setting('app.bypass_rls', true)::boolean = true) OR
                EXISTS (
                    SELECT 1 FROM stories s 
                    WHERE s.id = story_workflow_transitions.story_id 
                    AND s.space_id = ANY(get_current_user_space_ids())
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS asset_folders_tenant_isolation ON asset_folders');
        DB::statement('DROP POLICY IF EXISTS translation_groups_tenant_isolation ON translation_groups');
        DB::statement('DROP POLICY IF EXISTS workflow_states_tenant_isolation ON workflow_states');
        DB::statement('DROP POLICY IF EXISTS story_workflow_transitions_tenant_isolation ON story_workflow_transitions');

        // Remove foreign key constraints and columns from existing tables
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['workflow_state_id']);
            $table->dropIndex(['space_id', 'workflow_state_id']);
            $table->dropColumn('workflow_state_id');
            $table->dropForeign(['translation_group_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });

        // Drop GIN indexes
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS asset_folders_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS translation_groups_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS translation_groups_languages_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS workflow_states_metadata_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS workflow_states_permissions_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS workflow_states_transitions_gin_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS story_transitions_metadata_gin_idx');

        // Drop tables in reverse order
        Schema::dropIfExists('story_workflow_transitions');
        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('translation_groups');
        Schema::dropIfExists('asset_folders');
    }
};