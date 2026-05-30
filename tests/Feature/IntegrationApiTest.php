<?php

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivitySlot;
use App\Models\ActivityTypeVersion;
use App\Models\AuditLog;
use App\Models\Character;
use App\Models\DiscordUserIntegration;
use App\Models\Group;
use App\Models\IntegrationClient;
use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires an active integration api token', function () {
    $activity = Activity::factory()->create();

    $this->getJson(route('api.integrations.runs.show', $activity))
        ->assertUnauthorized();

    $this->getJson(route('api.integrations.runs.show', $activity), [
        'Authorization' => 'Bearer nope',
    ])->assertUnauthorized();
});

it('requires the integration client to have the needed scope', function () {
    $token = IntegrationClient::makePlainApiToken();
    $activity = Activity::factory()->create();

    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [],
        ]);

    $this->getJson(route('api.integrations.runs.show', $activity), [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('returns run status data for an authorized integration client', function () {
    $token = IntegrationClient::makePlainApiToken();
    $group = Group::factory()->create([
        'name' => 'Elemental Current',
        'slug' => 'elemental-current',
    ]);
    $version = ActivityTypeVersion::factory()->create([
        'name' => ['en' => 'Cloud of Darkness (Chaotic)'],
        'difficulty' => 'chaotic',
        'small_image_url' => '/storage/activities/cloud-small.webp',
        'banner_image_url' => '/storage/activities/cloud-banner.webp',
    ]);
    $activity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_version_id' => $version->id,
        'activity_type_id' => $version->activity_type_id,
        'title' => 'Friday prog',
        'status' => Activity::STATUS_SCHEDULED,
        'starts_at' => now()->addDay(),
        'duration_hours' => 2.5,
    ]);
    $client = IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $this->getJson(route('api.integrations.runs.show', $activity), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $activity->id)
        ->assertJsonPath('data.title', 'Friday prog')
        ->assertJsonPath('data.status', Activity::STATUS_SCHEDULED)
        ->assertJsonPath('data.group.name', 'Elemental Current')
        ->assertJsonPath('data.activity_type.name.en', 'Cloud of Darkness (Chaotic)')
        ->assertJsonPath('data.activity_type.difficulty', 'chaotic');

    expect($client->fresh()->last_api_used_at)->not->toBeNull();
});

it('returns the next six upcoming runs for a linked discord user', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $user = User::factory()->create();
    $character = Character::factory()->primary()->create([
        'user_id' => $user->id,
        'name' => 'Api Runner',
    ]);
    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => '123456789012345678',
        'username' => 'api-runner',
        'user_app_installed_at' => now(),
    ]);

    $group = Group::factory()->create([
        'name' => 'Integration Group',
        'slug' => 'integration-group',
    ]);
    $version = ActivityTypeVersion::factory()->create([
        'name' => ['en' => 'AAC Light-heavyweight M4 (Savage)'],
        'difficulty' => 'savage',
    ]);

    Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_version_id' => $version->id,
        'activity_type_id' => $version->activity_type_id,
        'status' => Activity::STATUS_SCHEDULED,
        'starts_at' => now()->subHour(),
    ]);

    $expectedIds = [];

    foreach (range(1, 7) as $hour) {
        $activity = Activity::factory()->create([
            'group_id' => $group->id,
            'activity_type_version_id' => $version->id,
            'activity_type_id' => $version->activity_type_id,
            'title' => 'Upcoming '.$hour,
            'status' => Activity::STATUS_SCHEDULED,
            'starts_at' => now()->addHours($hour),
        ]);

        ActivitySlot::factory()->assignedTo($character)->create([
            'activity_id' => $activity->id,
            'slot_key' => 'slot-'.$hour,
            'slot_label' => ['en' => 'Slot '.$hour],
        ]);

        if ($hour <= 6) {
            $expectedIds[] = $activity->id;
        }
    }

    $completedActivity = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_version_id' => $version->id,
        'activity_type_id' => $version->activity_type_id,
        'starts_at' => now()->addMinutes(30),
    ]);
    ActivitySlot::factory()->assignedTo($character)->create([
        'activity_id' => $completedActivity->id,
        'slot_key' => 'completed-slot',
        'slot_label' => ['en' => 'Completed Slot'],
    ]);

    $this->getJson(route('api.integrations.discord-users.upcoming-runs.index', [
        'discordUserId' => '123456789012345678',
    ]), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.0.id', $expectedIds[0])
        ->assertJsonPath('data.0.display_name', 'Upcoming 1')
        ->assertJsonPath('data.0.user_context.is_assigned', true)
        ->assertJsonPath('data.0.user_context.slot.character.name', 'Api Runner')
        ->assertJsonPath('data.5.id', $expectedIds[5]);
});

it('returns only ongoing applications for a linked discord user', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $user = User::factory()->create();
    $character = Character::factory()->primary()->create([
        'user_id' => $user->id,
        'name' => 'Api Applicant',
    ]);
    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => '223456789012345678',
        'username' => 'api-applicant',
        'user_app_installed_at' => now(),
    ]);

    $group = Group::factory()->create();
    $version = ActivityTypeVersion::factory()->create([
        'name' => ['en' => 'Futures Rewritten (Ultimate)'],
        'difficulty' => 'ultimate',
    ]);

    $activities = collect(range(1, 5))
        ->map(fn (int $index) => Activity::factory()->create([
            'group_id' => $group->id,
            'activity_type_version_id' => $version->id,
            'activity_type_id' => $version->activity_type_id,
            'title' => 'Application Run '.$index,
            'status' => $index === 5 ? Activity::STATUS_COMPLETE : Activity::STATUS_SCHEDULED,
            'starts_at' => now()->addDays($index),
        ]));

    $pending = ActivityApplication::factory()->create([
        'activity_id' => $activities[0]->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'status' => ActivityApplication::STATUS_PENDING,
        'submitted_at' => now()->subMinutes(3),
    ]);
    ActivityApplication::factory()->approved()->create([
        'activity_id' => $activities[1]->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'submitted_at' => now()->subMinutes(2),
    ]);
    $bench = ActivityApplication::factory()->create([
        'activity_id' => $activities[2]->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'status' => ActivityApplication::STATUS_ON_BENCH,
        'submitted_at' => now()->subMinute(),
    ]);
    ActivityApplication::factory()->declined()->create([
        'activity_id' => $activities[3]->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
    ]);
    ActivityApplication::factory()->create([
        'activity_id' => $activities[4]->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'status' => ActivityApplication::STATUS_PENDING,
    ]);

    $this->getJson(route('api.integrations.discord-users.applications.index', [
        'discordUserId' => '223456789012345678',
    ]), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $bench->id)
        ->assertJsonPath('data.0.character.name', 'Api Applicant')
        ->assertJsonPath('data.0.activity.display_name', 'Application Run 3')
        ->assertJsonPath('data.2.id', $pending->id);
});

it('returns not found when an integration asks for an unlinked discord user', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $this->getJson(route('api.integrations.discord-users.upcoming-runs.index', [
        'discordUserId' => '323456789012345678',
    ]), [
        'Authorization' => 'Bearer '.$token,
    ])->assertNotFound();
});

it('requires users read scope for discord primary character lookups', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $this->postJson(route('api.integrations.discord-users.primary-characters.index'), [
        'discord_user_ids' => ['423456789012345678'],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('returns primary characters for one or more linked discord users', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_USERS_READ,
            ],
        ]);

    $firstUser = User::factory()->create();
    Character::factory()->primary()->create([
        'user_id' => $firstUser->id,
        'name' => 'Lyra Dawn',
        'world' => 'Twintania',
        'datacenter' => 'Light',
    ]);
    DiscordUserIntegration::query()->create([
        'user_id' => $firstUser->id,
        'discord_user_id' => '523456789012345678',
        'username' => 'lyra',
        'user_app_installed_at' => now(),
    ]);

    $secondUser = User::factory()->create();
    DiscordUserIntegration::query()->create([
        'user_id' => $secondUser->id,
        'discord_user_id' => '623456789012345678',
        'username' => 'no-character',
        'user_app_installed_at' => now(),
    ]);

    $revokedUser = User::factory()->create();
    Character::factory()->primary()->create([
        'user_id' => $revokedUser->id,
        'name' => 'Revoked Link',
        'world' => 'Lich',
        'datacenter' => 'Light',
    ]);
    DiscordUserIntegration::query()->create([
        'user_id' => $revokedUser->id,
        'discord_user_id' => '723456789012345678',
        'username' => 'revoked',
        'user_app_installed_at' => now(),
        'revoked_at' => now(),
    ]);

    $this->postJson(route('api.integrations.discord-users.primary-characters.index'), [
        'discord_user_ids' => [
            '823456789012345678',
            '523456789012345678',
            '623456789012345678',
            '723456789012345678',
        ],
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.discord_user_id', '823456789012345678')
        ->assertJsonPath('data.0.linked', false)
        ->assertJsonPath('data.0.primary_character', null)
        ->assertJsonPath('data.1.discord_user_id', '523456789012345678')
        ->assertJsonPath('data.1.linked', true)
        ->assertJsonPath('data.1.primary_character.name', 'Lyra Dawn')
        ->assertJsonPath('data.1.primary_character.world', 'Twintania')
        ->assertJsonPath('data.1.primary_character.datacenter', 'Light')
        ->assertJsonPath('data.2.discord_user_id', '623456789012345678')
        ->assertJsonPath('data.2.linked', true)
        ->assertJsonPath('data.2.primary_character', null)
        ->assertJsonPath('data.3.discord_user_id', '723456789012345678')
        ->assertJsonPath('data.3.linked', false)
        ->assertJsonPath('data.3.primary_character', null);
});

it('requires users write scope to link a discord user by token', function () {
    $token = IntegrationClient::makePlainApiToken();

    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_USERS_READ,
            ],
        ]);

    $this->postJson(route('api.integrations.discord-users.link'), [
        'discord_user_id' => '923456789012345678',
        'token' => 'LINK-1234',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('links a discord user to the account matching a generated token', function () {
    $token = IntegrationClient::makePlainApiToken();

    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_USERS_WRITE,
            ],
        ]);

    $linkToken = 'FULLPARTY-LINK';
    $user = User::factory()->create([
        'discord_link_token_hash' => hash('sha256', $linkToken),
        'discord_link_token_expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson(route('api.integrations.discord-users.link'), [
        'discord_user_id' => '103456789012345678',
        'token' => $linkToken,
        'username' => 'linked-user',
        'global_name' => 'Linked User',
        'avatar_url' => 'https://cdn.discordapp.com/avatar.png',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertOk()
        ->assertJsonPath('data.linked', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.discord_user.id', '103456789012345678')
        ->assertJsonPath('data.discord_user.username', 'linked-user');

    $integration = DiscordUserIntegration::query()->sole();

    expect($integration->user_id)->toBe($user->id)
        ->and($integration->discord_user_id)->toBe('103456789012345678')
        ->and($integration->user_app_installed_at)->not->toBeNull()
        ->and($integration->revoked_at)->toBeNull()
        ->and($user->fresh()->discord_link_token_hash)->toBeNull()
        ->and($user->fresh()->discord_link_token_expires_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.discord_app.user_installed')->exists())->toBeTrue()
        ->and($user->onboardingState()->sole()->current_step)->toBe(UserOnboardingState::STEP_NOTIFICATIONS);
});

it('rejects expired or unknown discord user link tokens', function () {
    $token = IntegrationClient::makePlainApiToken();

    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_USERS_WRITE,
            ],
        ]);

    User::factory()->create([
        'discord_link_token_hash' => hash('sha256', 'EXPIRED-LINK'),
        'discord_link_token_expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('api.integrations.discord-users.link'), [
        'discord_user_id' => '113456789012345678',
        'token' => 'EXPIRED-LINK',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('does not let one discord user id link to another account', function () {
    $token = IntegrationClient::makePlainApiToken();

    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_USERS_WRITE,
            ],
        ]);

    $existingUser = User::factory()->create();
    DiscordUserIntegration::query()->create([
        'user_id' => $existingUser->id,
        'discord_user_id' => '123456789012345678',
        'username' => 'claimed',
        'user_app_installed_at' => now(),
    ]);

    $linkToken = 'OTHER-LINK';
    User::factory()->create([
        'discord_link_token_hash' => hash('sha256', $linkToken),
        'discord_link_token_expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson(route('api.integrations.discord-users.link'), [
        'discord_user_id' => '123456789012345678',
        'token' => $linkToken,
    ], [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('discord_user_id');
});
