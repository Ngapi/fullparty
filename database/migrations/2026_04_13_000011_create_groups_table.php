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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->text('banner_image_url')->nullable();
            $table->string('discord_invite_url')->nullable();
            $table->string('datacenter');
            $table->boolean('is_visible')->default(true);
            $table->string('slug', 8)->unique();
            $table->string('group_type', 32)->default('community');
            $table->string('join_mode', 32)->default('invite_only');
            $table->json('membership_application_schema')->nullable();
            $table->json('primary_focuses')->nullable();
            $table->string('experience_expectation', 64)->nullable();
            $table->string('voice_expectation', 64)->nullable();
            $table->json('preferred_languages')->nullable();
            $table->json('tags')->nullable();
            $table->string('active_timezone')->nullable();
            $table->json('active_days')->nullable();
            $table->string('active_start_time', 5)->nullable();
            $table->string('active_end_time', 5)->nullable();
            $table->timestamps();

            $table->index('group_type');
            $table->index('join_mode');
            $table->index('experience_expectation');
            $table->index('voice_expectation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
