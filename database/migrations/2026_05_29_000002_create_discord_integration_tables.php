<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_user_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('discord_user_id')->unique();
            $table->string('username')->nullable();
            $table->string('global_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestamp('user_app_installed_at')->nullable();
            $table->timestamp('last_seen_interaction_at')->nullable();
            $table->timestamp('last_delivery_failed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('revoked_at');
        });

        Schema::create('discord_guild_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discord_guild_id')->unique();
            $table->string('installed_by_discord_user_id')->nullable();
            $table->string('name')->nullable();
            $table->text('icon_url')->nullable();
            $table->string('permissions')->nullable();
            $table->timestamp('guild_installed_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index('removed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_guild_integrations');
        Schema::dropIfExists('discord_user_integrations');
    }
};
