<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\ScheduledRun;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GroupSeeder extends Seeder
{
    private const TARGET_GROUP_COUNT = 20;

    private const FALLBACK_REGION_BANNERS = [
        'EU' => '/prereqimages/forked.jpg',
        'NA' => '/ft.jpg',
        'JP' => '/prereqimages/chaotic.webp',
        'OCE' => '/prereqimages/chaotic_small.png',
    ];

    private const BANNER_WIDTH = 1500;

    private const BANNER_HEIGHT = 400;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = User::query()
            ->with('primaryCharacter')
            ->orderBy('id')
            ->get();

        if ($users->count() < 200) {
            throw new \RuntimeException('Expected a larger seeded user pool before seeding groups.');
        }

        $ownerPool = $users->shuffle()->values();
        $blueprints = collect($this->groupBlueprints())->take(self::TARGET_GROUP_COUNT - 1);

        $this->createSpecificForkedTowerGroup($users);

        $blueprints->each(function (array $blueprint, int $index) use ($ownerPool, $users) {
            /** @var User $owner */
            $owner = $ownerPool[$index];

            $group = Group::factory()->create([
                'owner_id' => $owner->id,
                'name' => $blueprint['name'],
                'description' => $blueprint['description'],
                'banner_image_url' => $blueprint['banner_image_url'],
                'discord_invite_url' => $blueprint['discord_invite_url'],
                'datacenter' => $blueprint['datacenter'],
                'is_public' => $blueprint['is_public'],
                'is_visible' => $blueprint['is_visible'],
                'slug' => $blueprint['slug'],
                'recruiting_status' => $blueprint['recruiting_status'],
                'primary_focuses' => $blueprint['primary_focuses'],
                'experience_expectation' => $blueprint['experience_expectation'],
                'voice_expectation' => $blueprint['voice_expectation'],
                'preferred_languages' => $blueprint['preferred_languages'],
                'tags' => $blueprint['tags'],
                'active_timezone' => $blueprint['active_timezone'],
                'active_days' => $blueprint['active_days'],
                'active_start_time' => $blueprint['active_start_time'],
                'active_end_time' => $blueprint['active_end_time'],
            ]);

            $this->seedMemberships($group, $users, $owner);
            $this->seedLegacyRunsForGroup($group, $owner->id);
        });
    }

    private function createSpecificForkedTowerGroup(Collection $users): void
    {
        /** @var User $owner */
        $owner = $users->firstOrFail();
        $createdAt = now()->subDays(40);
        $updatedAt = $createdAt->copy()->addHours(2);

        $group = Group::create([
            'owner_id' => $owner->id,
            'name' => 'Forked Tower Enjoyers',
            'description' => 'A chill but focused static for Forked Tower clears and progression nights.',
            'profile_picture_url' => null,
            'banner_image_url' => $this->seededBannerUrl('ftel', 'Forked Tower Enjoyers', 'Light'),
            'discord_invite_url' => 'https://discord.gg/ftel',
            'datacenter' => 'Light',
            'is_public' => true,
            'is_visible' => true,
            'slug' => 'ftel',
            'recruiting_status' => 'applications_open',
            'primary_focuses' => ['progression', 'clears', 'reclears'],
            'experience_expectation' => 'midcore',
            'voice_expectation' => 'required',
            'preferred_languages' => ['en', 'de', 'fr'],
            'tags' => ['Forked Tower', 'Static', 'Late Night'],
            'active_timezone' => 'Europe/London',
            'active_days' => ['wed', 'sun'],
            'active_start_time' => '19:30',
            'active_end_time' => '22:30',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $group->memberships()->create([
            'user_id' => $owner->id,
            'role' => GroupMembership::ROLE_OWNER,
            'joined_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $group->ensureSystemInvite();

        $this->seedMemberships($group, $users, $owner, 120);
        $this->seedLegacyRunsForGroup($group, $owner->id);
    }

    private function seedMemberships(Group $group, Collection $users, ?User $owner, ?int $forcedCount = null): void
    {
        $targetCount = $forcedCount ?? fake()->numberBetween(50, 200);
        $availableUsers = $users
            ->reject(fn (User $user) => $owner && $user->id === $owner->id)
            ->shuffle()
            ->values();

        $selectedUsers = $availableUsers->take(max(0, $targetCount - 1));
        $moderatorCount = min(fake()->numberBetween(0, 8), $selectedUsers->count());
        $moderatorIds = $selectedUsers->take($moderatorCount)->pluck('id')->all();

        $selectedUsers->each(function (User $user, int $index) use ($group, $moderatorIds): void {
            $joinedAt = $group->created_at->copy()->addHours($index + 1);

            $group->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'role' => in_array($user->id, $moderatorIds, true)
                        ? GroupMembership::ROLE_MODERATOR
                        : GroupMembership::ROLE_MEMBER,
                    'joined_at' => $joinedAt,
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ]
            );
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupBlueprints(): array
    {
        return [
            $this->groupBlueprint('Lich Nightwatch', 'Late evening raid planning and progression for players based on Lich and beyond.', 'lnight', 'Light', false, true, null, [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['progression', 'reclears'],
                'experience_expectation' => 'semi_hardcore',
                'voice_expectation' => 'required',
                'preferred_languages' => ['en', 'de'],
                'tags' => ['Late Night', 'Savage'],
                'active_days' => ['wed', 'sun'],
            ]),
            $this->groupBlueprint('Aether Vanguard', 'Structured raid planning and clean communication for progression-minded players.', 'aetherv', 'Aether', true, true, 'https://discord.gg/aetherv', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['progression', 'clears'],
                'experience_expectation' => 'midcore',
                'voice_expectation' => 'required',
                'tags' => ['Midcore', 'Rostered'],
            ]),
            $this->groupBlueprint('Chaos Forgehall', 'A small but organized EU group focused on consistent clears and static reliability.', 'forgehll', 'Chaos', false, true, null, [
                'recruiting_status' => 'closed',
                'primary_focuses' => ['clears', 'reclears'],
                'experience_expectation' => 'semi_hardcore',
                'voice_expectation' => 'preferred',
                'preferred_languages' => ['en', 'fr'],
                'tags' => ['Static', 'Consistency'],
            ]),
            $this->groupBlueprint('Primal Lantern', 'Flexible scheduling and friendly run leadership for savage and criterion groups.', 'primalan', 'Primal', true, false, 'https://discord.gg/primalan', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['progression', 'maps'],
                'experience_expectation' => 'casual',
                'voice_expectation' => 'preferred',
                'tags' => ['Criterion', 'Flexible'],
                'active_days' => ['thu', 'sat'],
            ]),
            $this->groupBlueprint('Meteor Archive', 'A planning-heavy group for spreadsheets, assignments, and clean execution.', 'metearch', 'Meteor', false, true, null, [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['progression', 'clears'],
                'experience_expectation' => 'hardcore',
                'voice_expectation' => 'preferred',
                'tags' => ['Prepared', 'Assignments'],
            ]),
            $this->groupBlueprint('Crystal Echo', 'Relaxed group culture with steady clears and mount farm organization.', 'crystlec', 'Crystal', true, true, 'https://discord.gg/crystlec', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['farming', 'mount_farming'],
                'experience_expectation' => 'beginner_friendly',
                'voice_expectation' => 'optional',
                'tags' => ['Casual', 'Friendly'],
            ]),
            $this->groupBlueprint('Mana Pioneers', 'JP-centered schedule coordination for players who like well-prepared runs.', 'manapion', 'Mana', false, true, null, [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['progression', 'reclears'],
                'experience_expectation' => 'midcore',
                'voice_expectation' => 'preferred',
                'preferred_languages' => ['ja'],
                'tags' => ['JP Prime Time', 'Prepared'],
            ]),
            $this->groupBlueprint('Dynamis Drift', 'Casual planning, alt runs, and open community clears.', 'dynamisd', 'Dynamis', true, false, 'https://discord.gg/dynamisd', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['casual_roulettes'],
                'experience_expectation' => 'casual',
                'voice_expectation' => 'optional',
                'tags' => ['Alt Friendly', 'Open Community'],
            ]),
            $this->groupBlueprint('Light Rampart', 'Hardcore raiding static focused on Ultimate and Savage content.', 'lightram', 'Light', false, true, null, [
                'recruiting_status' => 'closed',
                'primary_focuses' => ['progression', 'clears'],
                'experience_expectation' => 'hardcore',
                'voice_expectation' => 'required',
                'preferred_languages' => ['en'],
                'tags' => ['Ultimate', 'Hardcore'],
                'active_start_time' => '20:00',
                'active_end_time' => '23:00',
            ]),
            $this->groupBlueprint('Endwalker Raiders', 'Casual-midcore group for weekly clears and mount farms.', 'endraids', 'Aether', true, true, 'https://discord.gg/endraids', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['clears', 'mount_farming'],
                'experience_expectation' => 'casual',
                'voice_expectation' => 'preferred',
                'tags' => ['Weekly Clears', 'Mounts'],
            ]),
            $this->groupBlueprint('Crystal Cartel', 'Tightly coordinated scheduling for players who want polished group operations.', 'crystalc', 'Crystal', false, true, null, [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['clears', 'reclears'],
                'experience_expectation' => 'semi_hardcore',
                'voice_expectation' => 'required',
                'tags' => ['Organized', 'No Drama'],
            ]),
            $this->groupBlueprint('Savage Sunday', 'Weekend progression group with a focus on preparation and consistency.', 'savsundy', 'Primal', true, true, 'https://discord.gg/savsundy', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['progression', 'clears'],
                'experience_expectation' => 'midcore',
                'voice_expectation' => 'required',
                'tags' => ['Weekend', 'Savage'],
                'active_days' => ['sun'],
            ]),
            $this->groupBlueprint('Materia Crosswinds', 'Oceanic scheduling hub for progression and reclears.', 'matcross', 'Materia', true, true, 'https://discord.gg/matcross', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['progression', 'reclears'],
                'experience_expectation' => 'midcore',
                'voice_expectation' => 'preferred',
                'preferred_languages' => ['en'],
                'tags' => ['Oceanic', 'Night Raids'],
            ]),
            $this->groupBlueprint('Gaia Relay', 'JP relay-style organization for people juggling multiple raid groups.', 'gaiarely', 'Gaia', false, true, null, [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['maps'],
                'experience_expectation' => 'casual',
                'voice_expectation' => 'optional',
                'preferred_languages' => ['ja'],
                'tags' => ['Relay', 'Multi Group'],
            ]),
            $this->groupBlueprint('Aether Bloom', 'Friendly atmosphere with strong organizers and active learning runs.', 'aetherbl', 'Aether', true, true, 'https://discord.gg/aetherbl', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['progression'],
                'experience_expectation' => 'beginner_friendly',
                'voice_expectation' => 'preferred',
                'tags' => ['Learning Runs', 'Welcoming'],
            ]),
            $this->groupBlueprint('Chaos Lantern', 'EU scheduling and recruitment hub for mixed-skill groups.', 'chaoslan', 'Chaos', false, false, null, [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['casual_roulettes'],
                'experience_expectation' => 'mixed',
                'voice_expectation' => 'optional',
                'preferred_languages' => ['en', 'fr'],
                'tags' => ['Recruiting Hub', 'Mixed Skill'],
            ]),
            $this->groupBlueprint('Primal Garrison', 'Consistent moderation, roster stability, and no-drama clears.', 'primgarr', 'Primal', true, true, 'https://discord.gg/primgarr', [
                'recruiting_status' => 'applications_open',
                'primary_focuses' => ['clears', 'reclears'],
                'experience_expectation' => 'semi_hardcore',
                'voice_expectation' => 'required',
                'tags' => ['Roster Stability', 'Consistent'],
            ]),
            $this->groupBlueprint('Elemental Current', 'Steady run organization for Elemental players who want reliable uptime.', 'elemcurr', 'Elemental', true, true, 'https://discord.gg/elemcurr', [
                'recruiting_status' => 'looking_for_members',
                'primary_focuses' => ['clears', 'mount_farming'],
                'experience_expectation' => 'casual',
                'voice_expectation' => 'preferred',
                'preferred_languages' => ['ja', 'en'],
                'tags' => ['Reliable', 'Steady'],
            ]),
            $this->groupBlueprint('Meteor Signal', 'A quiet but efficient planning space for scheduled clears.', 'metersig', 'Meteor', false, true, null, [
                'recruiting_status' => 'closed',
                'primary_focuses' => ['clears'],
                'experience_expectation' => 'mixed',
                'voice_expectation' => 'optional',
                'tags' => ['Quiet', 'Efficient'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupBlueprint(
        string $name,
        string $description,
        string $slug,
        string $datacenter,
        bool $isPublic,
        bool $isVisible,
        ?string $discordInviteUrl,
        array $discovery = [],
    ): array {
        return array_merge($this->defaultDiscoveryMetadata($slug, $name, $datacenter, $discordInviteUrl), [
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'datacenter' => $datacenter,
            'is_public' => $isPublic,
            'is_visible' => $isVisible,
            'discord_invite_url' => $discordInviteUrl,
        ], $discovery);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDiscoveryMetadata(string $slug, string $name, string $datacenter, ?string $discordInviteUrl): array
    {
        $region = Group::regionForDatacenter($datacenter);

        return [
            'banner_image_url' => $this->seededBannerUrl($slug, $name, $datacenter),
            'recruiting_status' => 'looking_for_members',
            'primary_focuses' => ['clears'],
            'experience_expectation' => 'mixed',
            'voice_expectation' => $discordInviteUrl ? 'preferred' : 'optional',
            'preferred_languages' => $this->defaultLanguagesForRegion($region),
            'tags' => [],
            'active_timezone' => $this->defaultTimezoneForRegion($region),
            'active_days' => $this->defaultDaysForRegion($region),
            'active_start_time' => $this->defaultStartTimeForRegion($region),
            'active_end_time' => $this->defaultEndTimeForRegion($region),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defaultLanguagesForRegion(?string $region): array
    {
        return match ($region) {
            'EU' => ['en', 'de', 'fr'],
            'JP' => ['ja'],
            default => ['en'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function defaultDaysForRegion(?string $region): array
    {
        return match ($region) {
            'EU' => ['wed', 'fri', 'sun'],
            'JP' => ['wed', 'sat'],
            'OCE' => ['fri', 'sun'],
            default => ['tue', 'thu', 'sat'],
        };
    }

    private function defaultTimezoneForRegion(?string $region): string
    {
        return match ($region) {
            'EU' => 'Europe/London',
            'JP' => 'Asia/Tokyo',
            'OCE' => 'Australia/Sydney',
            default => 'America/New_York',
        };
    }

    private function defaultStartTimeForRegion(?string $region): string
    {
        return match ($region) {
            'EU' => '19:30',
            'JP' => '21:00',
            'OCE' => '19:00',
            default => '20:00',
        };
    }

    private function defaultEndTimeForRegion(?string $region): string
    {
        return match ($region) {
            'EU' => '22:30',
            'JP' => '23:30',
            'OCE' => '22:00',
            default => '23:00',
        };
    }

    private function seededBannerUrl(string $slug, string $name, string $datacenter): string
    {
        $region = Group::regionForDatacenter($datacenter);

        if (! function_exists('imagecreatetruecolor')) {
            return self::FALLBACK_REGION_BANNERS[$region ?? 'NA'] ?? '/ft.jpg';
        }

        $canvas = imagecreatetruecolor(self::BANNER_WIDTH, self::BANNER_HEIGHT);

        if (! $canvas) {
            return self::FALLBACK_REGION_BANNERS[$region ?? 'NA'] ?? '/ft.jpg';
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        [$baseRed, $baseGreen, $baseBlue] = $this->bannerPalette($slug.'-base', 24, 78);
        [$accentRed, $accentGreen, $accentBlue] = $this->bannerPalette($slug.'-accent', 90, 190);
        [$softRed, $softGreen, $softBlue] = $this->bannerPalette($slug.'-soft', 150, 235);

        $background = imagecolorallocate($canvas, $baseRed, $baseGreen, $baseBlue);
        imagefill($canvas, 0, 0, $background);

        $accentOne = imagecolorallocatealpha($canvas, $accentRed, $accentGreen, $accentBlue, 70);
        $accentTwo = imagecolorallocatealpha($canvas, $softRed, $softGreen, $softBlue, 88);
        $panelShade = imagecolorallocatealpha($canvas, 8, 10, 18, 48);
        $lineShade = imagecolorallocatealpha($canvas, 255, 255, 255, 108);
        $headline = imagecolorallocate($canvas, 248, 250, 252);
        $subhead = imagecolorallocate($canvas, 203, 213, 225);

        imagefilledellipse($canvas, 260, 120, 540, 540, $accentOne);
        imagefilledellipse($canvas, 1230, 300, 720, 720, $accentTwo);
        imagefilledrectangle($canvas, 0, 300, self::BANNER_WIDTH, self::BANNER_HEIGHT, $panelShade);

        foreach ([120, 260, 400, 540, 680, 820, 960, 1100, 1240, 1380] as $lineX) {
            imageline($canvas, $lineX, 0, $lineX - 140, self::BANNER_HEIGHT, $lineShade);
        }

        imagestring($canvas, 5, 48, 286, strtoupper($name), $headline);
        imagestring($canvas, 3, 50, 330, sprintf('%s  |  %s', strtoupper($datacenter), strtoupper($region ?? 'GLOBAL')), $subhead);

        ob_start();
        imagepng($canvas);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($binary) || $binary === '') {
            return self::FALLBACK_REGION_BANNERS[$region ?? 'NA'] ?? '/ft.jpg';
        }

        $path = 'groups/seeded-banners/'.$slug.'.png';
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function bannerPalette(string $seed, int $min, int $max): array
    {
        $hash = md5($seed);

        return [
            $this->bannerColorComponent(substr($hash, 0, 2), $min, $max),
            $this->bannerColorComponent(substr($hash, 2, 2), $min, $max),
            $this->bannerColorComponent(substr($hash, 4, 2), $min, $max),
        ];
    }

    private function bannerColorComponent(string $hex, int $min, int $max): int
    {
        $raw = hexdec($hex);
        $range = max(1, $max - $min);

        return $min + ($raw % ($range + 1));
    }

    private function seedLegacyRunsForGroup(Group $group, int $ownerId): void
    {
        $runCount = fake()->numberBetween(1, 4);
        $statuses = [
            ScheduledRun::STATUS_SCHEDULED,
            ScheduledRun::STATUS_UPCOMING,
            ScheduledRun::STATUS_PLANNED,
        ];

        foreach (range(1, $runCount) as $runIndex) {
            $runTimestamp = $group->updated_at->copy()->addHours($runIndex);

            $group->scheduledRuns()->create([
                'organized_by_user_id' => $ownerId,
                'status' => fake()->randomElement($statuses),
                'created_at' => $runTimestamp,
                'updated_at' => $runTimestamp,
            ]);
        }
    }
}
