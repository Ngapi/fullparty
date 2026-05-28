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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_type_version_id')->constrained('activity_type_versions')->restrictOnDelete();
            $table->foreignId('organized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organized_by_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('status');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->decimal('duration_hours', 4, 1)->unsigned()->default(2.0);
            $table->string('target_prog_point_key')->nullable();
            $table->string('datacenter')->nullable();
            $table->string('intensity')->default('casual');
            $table->unsignedSmallInteger('min_item_level')->nullable();
            $table->boolean('beginner_friendly')->default(false);
            $table->string('run_style')->default('progression');
            $table->boolean('is_public')->default(true);
            $table->boolean('needs_application')->default(true);
            $table->boolean('allow_guest_applications')->default(false);
            $table->string('secret_key', 64)->nullable();
            $table->json('settings')->nullable();
            $table->string('progress_entry_mode')->nullable();
            $table->text('progress_link_url')->nullable();
            $table->text('progress_notes')->nullable();
            $table->string('furthest_progress_key')->nullable();
            $table->decimal('furthest_progress_percent', 5, 2)->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('progress_recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('progress_recorded_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['group_id', 'starts_at']);
            $table->index(['group_id', 'is_public']);
            $table->unique('secret_key');
            $table->index(['datacenter', 'starts_at']);
            $table->index(['intensity', 'starts_at']);
            $table->index(['run_style', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
