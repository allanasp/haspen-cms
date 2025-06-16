<?php

declare(strict_types=1);

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
        Schema::table('components', function (Blueprint $table) {
            // Component inheritance system
            $table->unsignedBigInteger('parent_component_id')->nullable()->after('space_id')->comment('Parent component for inheritance');
            $table->jsonb('inherited_fields')->nullable()->after('schema')->comment('Fields inherited from parent');
            $table->jsonb('override_fields')->nullable()->after('inherited_fields')->comment('Fields that override parent');
            $table->boolean('allow_inheritance')->default(true)->after('is_nestable')->comment('Allow this component to be inherited');
            
            // Component variants system
            $table->string('variant_group')->nullable()->after('technical_name')->comment('Group name for component variants');
            $table->string('variant_name')->nullable()->after('variant_group')->comment('Specific variant name');
            $table->jsonb('variant_config')->nullable()->after('override_fields')->comment('Variant-specific configuration');

            // Foreign key for parent component
            $table->foreign('parent_component_id')->references('id')->on('components')->onDelete('set null');
            
            // Indexes for performance
            $table->index('parent_component_id');
            $table->index(['variant_group', 'variant_name']);
            $table->index('allow_inheritance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropForeign(['parent_component_id']);
            $table->dropIndex(['parent_component_id']);
            $table->dropIndex(['variant_group', 'variant_name']);
            $table->dropIndex(['allow_inheritance']);
            
            $table->dropColumn([
                'parent_component_id',
                'inherited_fields',
                'override_fields',
                'allow_inheritance',
                'variant_group',
                'variant_name',
                'variant_config'
            ]);
        });
    }
};