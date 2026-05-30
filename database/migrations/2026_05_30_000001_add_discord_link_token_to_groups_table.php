<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->string('discord_link_token_hash')->nullable()->after('discord_invite_url');
            $table->timestamp('discord_link_token_expires_at')->nullable()->after('discord_link_token_hash');

            $table->index('discord_link_token_hash');
            $table->index('discord_link_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropIndex(['discord_link_token_hash']);
            $table->dropIndex(['discord_link_token_expires_at']);
            $table->dropColumn(['discord_link_token_hash', 'discord_link_token_expires_at']);
        });
    }
};
