<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('story_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            
            // Story content at the time of version creation
            $table->string('name');
            $table->string('slug');
            $table->json('content')->nullable();
            $table->string('status');
            $table->string('language', 10)->default('en');
            $table->integer('position')->default(0);
            
            // SEO metadata
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            
            // Timestamps from original story
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            
            // Version metadata
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            // Indexes
            $table->index(['story_id', 'version_number']);
            $table->index(['story_id', 'created_at']);
            $table->unique(['story_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_versions');
    }
};
