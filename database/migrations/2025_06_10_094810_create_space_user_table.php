<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Pivot table linking users to spaces with role-based access control.
     * Enables multi-tenant user management with role assignments.
     */
    public function up(): void
    {
        Schema::create('space_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id')->comment('Reference to space');
            $table->unsignedBigInteger('user_id')->comment('Reference to user');
            $table->unsignedBigInteger('role_id')->comment('User role in this space');
            
            // Access control
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active')->comment('User status in space');
            $table->timestamp('joined_at')->useCurrent()->comment('When user joined the space');
            $table->timestamp('last_accessed_at')->nullable()->comment('Last space access timestamp');
            $table->unsignedBigInteger('invited_by')->nullable()->comment('User who sent the invitation');
            
            // Additional permissions override
            $table->jsonb('custom_permissions')->nullable()->comment('Space-specific permission overrides');
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('space_id')->references('id')->on('spaces')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraint - user can only have one role per space
            $table->unique(['space_id', 'user_id'], 'space_user_unique');
            
            // Indexes for performance
            $table->index(['space_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['role_id']);
            $table->index('last_accessed_at');
            
            // GIN index for custom permissions
            $table->rawIndex('USING GIN (custom_permissions)', 'space_user_permissions_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_user');
    }
};
