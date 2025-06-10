<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Roles table defines user permissions and access levels within spaces.
     * Supports Storyblok-style role-based access control.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Role name (admin, editor, viewer, etc.)');
            $table->string('slug')->comment('URL-friendly role identifier');
            $table->text('description')->nullable()->comment('Role description');
            
            // Permissions configuration
            $table->jsonb('permissions')->default('{}')->comment('Detailed permissions configuration');
            $table->boolean('is_system_role')->default(false)->comment('Whether role is system-defined');
            $table->boolean('is_default')->default(false)->comment('Default role for new users');
            
            // Hierarchy and inheritance
            $table->integer('priority')->default(0)->comment('Role priority for conflict resolution');
            $table->unsignedBigInteger('parent_role_id')->nullable()->comment('Parent role for inheritance');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('parent_role_id')->references('id')->on('roles')->onDelete('set null');
            
            // Indexes
            $table->unique(['slug']);
            $table->index(['is_system_role', 'is_default']);
            $table->index('priority');
            
            // GIN index for JSONB permissions
            $table->rawIndex('USING GIN (permissions)', 'roles_permissions_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
