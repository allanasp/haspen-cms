<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Assets table manages file uploads and media with rich metadata.
     * Supports image transformations, CDN integration, and organization.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id')->comment('Space this asset belongs to');
            $table->uuid('uuid')->comment('Public UUID for API exposure');
            $table->string('filename')->comment('Original filename');
            $table->string('name')->nullable()->comment('User-friendly asset name');
            $table->text('description')->nullable()->comment('Asset description');
            $table->text('alt_text')->nullable()->comment('Alternative text for accessibility');

            // File information
            $table->string('content_type')->comment('MIME type of the file');
            $table->bigInteger('file_size')->comment('File size in bytes');
            $table->string('file_hash', 64)->comment('SHA-256 hash for deduplication');
            $table->string('extension', 10)->comment('File extension');

            // Storage information
            $table->string('storage_disk')->default('public')->comment('Storage disk/driver');
            $table->string('storage_path')->comment('Path within storage disk');
            $table->string('public_url')->nullable()->comment('Public accessible URL');
            $table->string('cdn_url')->nullable()->comment('CDN URL if available');

            // Image-specific metadata
            $table->integer('width')->nullable()->comment('Image width in pixels');
            $table->integer('height')->nullable()->comment('Image height in pixels');
            $table->decimal('aspect_ratio', 8, 4)->nullable()->comment('Image aspect ratio');
            $table->string('dominant_color', 7)->nullable()->comment('Dominant color hex code');
            $table->boolean('has_transparency')->default(false)->comment('Whether image has transparency');

            // Processing and optimization
            $table->jsonb('processing_data')->nullable()->comment('Image processing/optimization data');
            $table->jsonb('variants')->default('{}')->comment('Generated image variants/sizes');
            $table->boolean('is_processed')->default(false)->comment('Whether asset has been processed');
            $table->timestamp('processed_at')->nullable()->comment('Processing completion time');

            // Organization and taxonomy
            $table->unsignedBigInteger('folder_id')->nullable()->comment('Asset folder/category');
            $table->jsonb('tags')->default('[]')->comment('Asset tags for organization');
            $table->jsonb('custom_fields')->default('{}')->comment('Custom metadata fields');

            // Usage and analytics
            $table->integer('download_count')->default(0)->comment('Number of downloads');
            $table->timestamp('last_accessed_at')->nullable()->comment('Last access timestamp');
            $table->jsonb('usage_stats')->default('{}')->comment('Detailed usage statistics');

            // External integrations
            $table->string('external_id')->nullable()->comment('External service identifier');
            $table->jsonb('external_data')->nullable()->comment('External service metadata');

            // Access control
            $table->boolean('is_public')->default(true)->comment('Whether asset is publicly accessible');
            $table->jsonb('allowed_roles')->nullable()->comment('Roles that can access this asset');
            $table->timestamp('expires_at')->nullable()->comment('Asset expiration time');

            // Audit fields
            $table->unsignedBigInteger('uploaded_by')->comment('User who uploaded the asset');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated the asset');

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('space_id')->references('id')->on('spaces')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Unique constraints
            $table->unique(['space_id', 'uuid'], 'assets_space_uuid_unique');
            $table->unique(['space_id', 'file_hash'], 'assets_space_hash_unique');

            // Indexes for performance
            $table->index(['space_id', 'content_type']);
            $table->index(['space_id', 'extension']);
            $table->index(['space_id', 'file_size']);
            $table->index(['space_id', 'is_public']);
            $table->index(['space_id', 'folder_id']);
            $table->index(['file_hash']);
            $table->index(['is_processed']);
            $table->index(['expires_at']);
            $table->index(['last_accessed_at']);
            $table->index(['download_count']);
        });

        // Create GIN indexes for JSONB fields
        DB::statement('CREATE INDEX IF NOT EXISTS assets_processing_data_gin_idx ON assets USING GIN (processing_data)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_variants_gin_idx ON assets USING GIN (variants)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_tags_gin_idx ON assets USING GIN (tags)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_custom_fields_gin_idx ON assets USING GIN (custom_fields)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_usage_stats_gin_idx ON assets USING GIN (usage_stats)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_external_data_gin_idx ON assets USING GIN (external_data)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_allowed_roles_gin_idx ON assets USING GIN (allowed_roles)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
