<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordGuildIntegration;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IntegrationGuildController extends Controller
{
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discord_guild_id' => ['required', 'string', 'max:64', 'regex:/^\d{1,32}$/'],
            'token' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:120'],
            'icon_url' => ['nullable', 'url:http,https', 'max:2048'],
            'permissions' => ['nullable', 'string', 'max:64'],
        ]);

        $group = Group::query()
            ->where('discord_link_token_hash', hash('sha256', $validated['token']))
            ->where('discord_link_token_expires_at', '>', now())
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'token' => 'discord_guild_link_token_invalid',
            ]);
        }

        $existingGuildIntegration = DiscordGuildIntegration::query()
            ->where('discord_guild_id', $validated['discord_guild_id'])
            ->whereNull('removed_at')
            ->first();

        if ($existingGuildIntegration
            && $existingGuildIntegration->group_id !== null
            && (int) $existingGuildIntegration->group_id !== (int) $group->id) {
            throw ValidationException::withMessages([
                'discord_guild_id' => 'discord_guild_already_linked',
            ]);
        }

        DiscordGuildIntegration::query()
            ->where('group_id', $group->id)
            ->whereNull('removed_at')
            ->where('discord_guild_id', '!=', $validated['discord_guild_id'])
            ->update(['group_id' => null]);

        $integration = DiscordGuildIntegration::query()->updateOrCreate([
            'discord_guild_id' => $validated['discord_guild_id'],
        ], [
            'group_id' => $group->id,
            'name' => $validated['name'] ?? $existingGuildIntegration?->name,
            'icon_url' => $validated['icon_url'] ?? $existingGuildIntegration?->icon_url,
            'permissions' => $validated['permissions'] ?? $existingGuildIntegration?->permissions,
            'guild_installed_at' => $existingGuildIntegration?->guild_installed_at ?? now(),
            'removed_at' => null,
        ]);

        $group->forceFill([
            'discord_link_token_hash' => null,
            'discord_link_token_expires_at' => null,
        ])->save();

        return response()->json([
            'data' => [
                'linked' => true,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                ],
                'guild' => [
                    'id' => $integration->id,
                    'discord_guild_id' => $integration->discord_guild_id,
                    'name' => $integration->name,
                    'icon_url' => $integration->icon_url,
                    'guild_installed_at' => $integration->guild_installed_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
