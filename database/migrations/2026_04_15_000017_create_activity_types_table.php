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
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('draft_name');
            $table->json('draft_description')->nullable();
            $table->text('draft_small_image_url')->nullable();
            $table->text('draft_banner_image_url')->nullable();
            $table->string('draft_difficulty')->default('normal');
            $table->unsignedSmallInteger('draft_default_min_item_level')->nullable();
            $table->json('draft_layout_schema');
            $table->json('draft_slot_schema');
            $table->json('draft_application_schema');
            $table->json('draft_roster_summary_presets')->nullable();
            $table->json('draft_progress_schema')->nullable();
            $table->unsignedInteger('draft_bench_size')->default(0);
            $table->json('draft_prog_points')->nullable();
            $table->unsignedInteger('draft_fflogs_zone_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_types');
    }
};
