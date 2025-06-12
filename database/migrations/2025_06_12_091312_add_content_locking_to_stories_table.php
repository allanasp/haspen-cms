<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Content locking fields
            $table->unsignedBigInteger('locked_by')->nullable()->comment('User who has locked the story for editing');
            $table->timestamp('locked_at')->nullable()->comment('When the story was locked');
            $table->timestamp('lock_expires_at')->nullable()->comment('When the lock expires');
            $table->string('lock_session_id')->nullable()->comment('Session ID that created the lock');
            
            // Add foreign key for locked_by
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            
            // Add index for lock queries
            $table->index(['locked_by', 'locked_at']);
            $table->index(['lock_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropIndex(['locked_by', 'locked_at']);
            $table->dropIndex(['lock_expires_at']);
            $table->dropColumn(['locked_by', 'locked_at', 'lock_expires_at', 'lock_session_id']);
        });
    }
};