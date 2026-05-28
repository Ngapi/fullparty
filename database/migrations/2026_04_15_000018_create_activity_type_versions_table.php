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
        Schema::create('activity_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('name');
            $table->json('description')->nullable();
            $table->text('small_image_url')->nullable();
            $table->text('banner_image_url')->nullable();
            $table->string('difficulty')->default('normal');
            $table->unsignedSmallInteger('default_min_item_level')->nullable();
            $table->json('layout_schema');
            $table->json('slot_schema');
            $table->json('application_schema');
            $table->json('roster_summary_presets')->nullable();
            $table->json('progress_schema')->nullable();
            $table->unsignedInteger('bench_size')->default(0);
            $table->json('prog_points')->nullable();
            $table->unsignedInteger('fflogs_zone_id')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['activity_type_id', 'version']);
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->foreignId('current_published_version_id')
                ->nullable()
                ->constrained('activity_type_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_published_version_id');
        });

        Schema::dropIfExists('activity_type_versions');
    }
};
