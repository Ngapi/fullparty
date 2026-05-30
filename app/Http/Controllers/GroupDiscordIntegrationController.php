<?php

namespace App\Http\Controllers;

use App\Models\DiscordGuildIntegration;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GroupDiscordIntegrationController extends Controller
{
    public function show(Group $group): Response
    {
        $this->authorizeOwner($group);

        $group->loadMissing(['memberships', 'activeDiscordGuildIntegration']);

        return Inertia::render('Dashboard/Groups/DiscordIntegration', [
            'group' => $this->serializeGroup($group),
            'integration' => $this->serializeIntegration($group->activeDiscordGuildIntegration),
            'inviteUrl' => route('discord-app.guild.redirect'),
        ]);
    }

    public function generateToken(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeOwner($group);

        $plainToken = Str::upper(Str::random(8)).'-'.Str::upper(Str::random(8));
        $expiresAt = now()->addMinutes(30);

        $group->forceFill([
            'discord_link_token_hash' => hash('sha256', $plainToken),
            'discord_link_token_expires_at' => $expiresAt,
        ])->save();

        return back()
            ->with('success', 'discord_guild_link_token_generated')
            ->with('flash_data', [
                'discord_guild_link_token' => [
                    'token' => $plainToken,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroup(Group $group): array
    {
        $currentUserId = auth()->id();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'current_user_role' => $group->memberships
                ->firstWhere('user_id', $currentUserId)
                ?->role,
            'permissions' => [
                'can_manage_group' => $group->isOwnedBy($currentUserId),
                'can_manage_members' => $group->hasModeratorAccess($currentUserId),
                'can_manage_discovery' => $group->hasAdminAccess($currentUserId),
                'can_manage_activities' => $group->hasModeratorAccess($currentUserId),
                'can_view_members' => $group->hasMember($currentUserId),
                'can_review_membership_applications' => $group->usesMembershipApplications() && $group->hasModeratorAccess($currentUserId),
                'can_manage_membership_application_form' => $group->usesMembershipApplications() && $group->hasAdminAccess($currentUserId),
            ],
            'discord_link_token_expires_at' => $group->discord_link_token_expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeIntegration(?DiscordGuildIntegration $integration): ?array
    {
        if (! $integration) {
            return null;
        }

        return [
            'id' => $integration->id,
            'discord_guild_id' => $integration->discord_guild_id,
            'name' => $integration->name,
            'icon_url' => $integration->icon_url,
            'permissions' => $integration->permissions,
            'guild_installed_at' => $integration->guild_installed_at?->toIso8601String(),
            'updated_at' => $integration->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeOwner(Group $group): void
    {
        if (! $group->isOwnedBy(auth()->id())) {
            abort(403);
        }
    }
}
