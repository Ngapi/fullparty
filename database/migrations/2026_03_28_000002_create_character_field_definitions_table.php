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
        Schema::create('character_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Display label, e.g., "Phantom Level"
            $table->string('slug')->unique(); // Key for storage, e.g., "phantom_level"
            $table->enum('type', ['text', 'number', 'date', 'textarea', 'select', 'checkbox'])->default('text');
            $table->text('description')->nullable();
            $table->string('group')->default('profile');
            $table->json('display_contexts')->nullable();
            $table->string('source_type')->default('user');
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->json('tags')->nullable();
            $table->json('validation_rules')->nullable(); // Store validation config as JSON
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('group');
            $table->index('source_type');
            $table->index('is_visible');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_field_definitions');
    }
};
