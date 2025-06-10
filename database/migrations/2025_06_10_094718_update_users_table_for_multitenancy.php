<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update users table to support multi-tenant CMS functionality.
     * Adds fields for user profiles, preferences, and audit tracking.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add UUID for public API exposure
            $table->uuid('uuid')->unique()->after('id')->comment('Public UUID for API exposure');
            
            // User profile information
            $table->string('first_name')->nullable()->after('name')->comment('User first name');
            $table->string('last_name')->nullable()->after('first_name')->comment('User last name');
            $table->string('avatar_url')->nullable()->after('email')->comment('User avatar image URL');
            $table->string('timezone', 50)->default('UTC')->after('avatar_url')->comment('User timezone');
            $table->string('language', 10)->default('en')->after('timezone')->comment('User preferred language');
            
            // Account status and security
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('email_verified_at')->comment('User account status');
            $table->timestamp('last_login_at')->nullable()->after('status')->comment('Last login timestamp');
            $table->string('last_login_ip')->nullable()->after('last_login_at')->comment('Last login IP address');
            $table->boolean('two_factor_enabled')->default(false)->after('last_login_ip')->comment('Two-factor authentication enabled');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled')->comment('2FA secret key');
            
            // User preferences and settings
            $table->jsonb('preferences')->default('{}')->after('two_factor_secret')->comment('User preferences and settings');
            $table->jsonb('metadata')->default('{}')->after('preferences')->comment('Additional user metadata');
            
            // Add soft deletes
            $table->softDeletes()->after('updated_at');
            
            // Add indexes
            $table->index('status');
            $table->index(['status', 'last_login_at']);
            $table->index('timezone');
            $table->index('language');
            
            // GIN indexes for JSONB fields
            $table->rawIndex('USING GIN (preferences)', 'users_preferences_gin_idx');
            $table->rawIndex('USING GIN (metadata)', 'users_metadata_gin_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid',
                'first_name',
                'last_name',
                'avatar_url',
                'timezone',
                'language',
                'status',
                'last_login_at',
                'last_login_ip',
                'two_factor_enabled',
                'two_factor_secret',
                'preferences',
                'metadata',
                'deleted_at'
            ]);
        });
    }
};
