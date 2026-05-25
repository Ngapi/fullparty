<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGroupSettingsRequest;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Services\AuditLogger;
use App\Services\ManagedImageStorage;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSeverity;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GroupSettingsController extends Controller
{
    private const IMAGE_DIRECTORY = 'groups';

    public function __construct(
        private readonly ManagedImageStorage $managedImageStorage,
        private readonly AuditLogger $auditLogger,
        private readonly GroupDiscoveryBadgePalette $groupDiscoveryBadgePalette,
    ) {}

    public function show(Group $group): Response
    {
        $group->load([
            'owner',
            'memberships.user',
            'invites.creator',
        ]);

        $this->authorizeModeratorAccess($group);

        return Inertia::render('Dashboard/Groups/Settings/Index', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'profile_picture_url' => $group->profile_picture_url,
                'banner_image_url' => $group->banner_image_url,
                'discord_invite_url' => $group->discord_invite_url,
                'datacenter' => $group->datacenter,
                'region' => $group->inferredRegion(),
                'is_public' => $group->is_public,
                'is_visible' => $group->is_visible,
                'slug' => $group->slug,
                'group_type' => $group->group_type,
                'recruiting_status' => $group->recruiting_status,
                'primary_focuses' => $group->primary_focuses ?? [],
                'experience_expectation' => $group->experience_expectation,
                'voice_expectation' => $group->voice_expectation,
                'preferred_languages' => $group->preferred_languages ?? [],
                'tags' => $group->tags ?? [],
                'active_timezone' => $group->active_timezone,
                'active_days' => $group->active_days ?? [],
                'active_start_time' => $group->active_start_time,
                'active_end_time' => $group->active_end_time,
                'badge_meta' => $this->groupDiscoveryBadgePalette->badgeMetaForGroup($group),
                'owner' => [
                    'id' => $group->owner?->id,
                    'name' => $group->owner?->name,
                    'avatar_url' => $group->owner?->avatar_url,
                ],
                'current_user_role' => $group->memberships
                    ->firstWhere('user_id', auth()->id())
                    ?->role,
                'permissions' => [
                    'can_manage_group' => $group->isOwnedBy(auth()->id()),
                    'can_manage_members' => $group->hasModeratorAccess(auth()->id()),
                    'can_manage_invites' => $group->usesCommunityJoinFlow()
                        && $group->hasModeratorAccess(auth()->id()),
                    'can_transfer_ownership' => $group->isOwnedBy(auth()->id()),
                    'can_view_members' => $group->hasMember(auth()->id()),
                ],
                'members' => $group->memberships
                    ->sortBy(function (GroupMembership $membership) {
                        return array_search($membership->role, GroupMembership::ROLES, true);
                    })
                    ->values()
                    ->map(fn (GroupMembership $membership) => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'avatar_url' => $membership->user->avatar_url,
                        'role' => $membership->role,
                        'joined_at' => $membership->joined_at,
                    ]),
                'invites' => $group->usesCommunityJoinFlow()
                    ? $group->invites
                        ->sortByDesc('created_at')
                        ->values()
                        ->map(fn ($invite) => [
                            'id' => $invite->id,
                            'token' => $invite->token,
                            'is_system' => $invite->is_system,
                            'uses' => $invite->uses,
                            'max_uses' => $invite->max_uses,
                            'expires_at' => $invite->expires_at,
                            'created_by' => $invite->creator?->name,
                            'created_at' => $invite->created_at,
                        ])
                    : [],
            ],
        ]);
    }

    public function update(UpdateGroupSettingsRequest $request, Group $group): RedirectResponse
    {
        $group->loadMissing('memberships', 'invites');

        $this->authorizeOwnerAccess($group);
        $validated = $request->validated();

        $profilePictureUrl = $this->managedImageStorage->replaceUploadedImageIfPresent(
            currentUrl: $group->profile_picture_url,
            file: $request->file('profile_picture'),
            directory: self::IMAGE_DIRECTORY,
            shouldProcess: true
        );
        $bannerImageUrl = $this->managedImageStorage->replaceUploadedImageIfPresent(
            currentUrl: $group->banner_image_url,
            file: $request->file('banner_image'),
            directory: self::IMAGE_DIRECTORY,
        );

        $originalValues = [
            'name' => $group->name,
            'description' => $group->description,
            'profile_picture_url' => $group->profile_picture_url,
            'banner_image_url' => $group->banner_image_url,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'recruiting_status' => $group->recruiting_status,
            'primary_focuses' => $group->primary_focuses ?? [],
            'experience_expectation' => $group->experience_expectation,
            'voice_expectation' => $group->voice_expectation,
            'preferred_languages' => $group->preferred_languages ?? [],
            'tags' => $group->tags ?? [],
            'active_timezone' => $group->active_timezone,
            'active_days' => $group->active_days ?? [],
            'active_start_time' => $group->active_start_time,
            'active_end_time' => $group->active_end_time,
        ];

        DB::transaction(function () use ($group, $validated, $profilePictureUrl, $bannerImageUrl) {
            $group->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'profile_picture_url' => $profilePictureUrl,
                'banner_image_url' => $bannerImageUrl,
                'discord_invite_url' => $validated['discord_invite_url'] ?? null,
                'datacenter' => $validated['datacenter'],
                'is_public' => $validated['is_public'],
                'is_visible' => $validated['is_visible'],
                'recruiting_status' => Arr::exists($validated, 'recruiting_status')
                    ? ($validated['recruiting_status'] ?? null)
                    : $group->recruiting_status,
                'primary_focuses' => Arr::exists($validated, 'primary_focuses')
                    ? ($validated['primary_focuses'] ?? [])
                    : ($group->primary_focuses ?? []),
                'experience_expectation' => Arr::exists($validated, 'experience_expectation')
                    ? ($validated['experience_expectation'] ?? null)
                    : $group->experience_expectation,
                'voice_expectation' => Arr::exists($validated, 'voice_expectation')
                    ? ($validated['voice_expectation'] ?? null)
                    : $group->voice_expectation,
                'preferred_languages' => Arr::exists($validated, 'preferred_languages')
                    ? ($validated['preferred_languages'] ?? [])
                    : ($group->preferred_languages ?? []),
                'tags' => Arr::exists($validated, 'tags')
                    ? ($validated['tags'] ?? [])
                    : ($group->tags ?? []),
                'active_timezone' => Arr::exists($validated, 'active_timezone')
                    ? ($validated['active_timezone'] ?? null)
                    : $group->active_timezone,
                'active_days' => Arr::exists($validated, 'active_days')
                    ? ($validated['active_days'] ?? [])
                    : ($group->active_days ?? []),
                'active_start_time' => Arr::exists($validated, 'active_start_time')
                    ? ($validated['active_start_time'] ?? null)
                    : $group->active_start_time,
                'active_end_time' => Arr::exists($validated, 'active_end_time')
                    ? ($validated['active_end_time'] ?? null)
                    : $group->active_end_time,
            ]);

            if ($group->is_public && $group->usesCommunityJoinFlow()) {
                $group->ensureSystemInvite();
            } else {
                $group->removeSystemInvite();
            }
        });

        $updatedValues = [
            'name' => $group->name,
            'description' => $group->description,
            'profile_picture_url' => $group->profile_picture_url,
            'banner_image_url' => $group->banner_image_url,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'recruiting_status' => $group->recruiting_status,
            'primary_focuses' => $group->primary_focuses ?? [],
            'experience_expectation' => $group->experience_expectation,
            'voice_expectation' => $group->voice_expectation,
            'preferred_languages' => $group->preferred_languages ?? [],
            'tags' => $group->tags ?? [],
            'active_timezone' => $group->active_timezone,
            'active_days' => $group->active_days ?? [],
            'active_start_time' => $group->active_start_time,
            'active_end_time' => $group->active_end_time,
        ];

        $changedFields = collect($updatedValues)
            ->keys()
            ->filter(fn (string $field) => $originalValues[$field] !== $updatedValues[$field])
            ->values()
            ->all();

        if ($changedFields !== []) {
            $this->auditLogger->log(
                action: 'group.updated',
                severity: AuditSeverity::MODERATION_CHANGE,
                scopeType: AuditScope::GROUP,
                scopeId: $group->id,
                message: 'audit_log.events.group.updated',
                actor: auth()->user(),
                subject: $group,
                metadata: [
                    'changed_fields' => $changedFields,
                    'changes' => $this->buildChangeMetadata($originalValues, $updatedValues),
                ],
            );
        }

        return redirect()->back()->with('success', 'group_updated');
    }

    private function authorizeOwnerAccess(Group $group): void
    {
        if (! $group->isOwnedBy(auth()->id())) {
            abort(403);
        }
    }

    private function authorizeModeratorAccess(Group $group): void
    {
        if (! $group->hasModeratorAccess(auth()->id())) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $originalValues
     * @param  array<string, mixed>  $updatedValues
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildChangeMetadata(array $originalValues, array $updatedValues): array
    {
        return collect($updatedValues)
            ->keys()
            ->filter(fn (string $field) => $originalValues[$field] !== $updatedValues[$field])
            ->mapWithKeys(fn (string $field) => [
                $field => [
                    'old' => $originalValues[$field],
                    'new' => $updatedValues[$field],
                ],
            ])
            ->all();
    }
}
