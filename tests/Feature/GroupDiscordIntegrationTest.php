<?php

use App\Models\DiscordGuildIntegration;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\IntegrationClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the discord integration page to group owners only', function () {
    $owner = User::factory()->create();
    $moderator = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Discord Group',
    ]);
    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $this->actingAs($moderator)
        ->get(route('groups.dashboard.discord-integration', $group))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('groups.dashboard.discord-integration', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/DiscordIntegration')
            ->where('group.name', 'Discord Group')
            ->where('group.permissions.can_manage_group', true)
            ->where('integration', null)
            ->where('inviteUrl', route('discord-app.guild.redirect'))
        );
});

it('lets group owners generate a short lived discord link token', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('groups.dashboard.discord-integration.link-token', $group))
        ->assertRedirect()
        ->assertSessionHas('success', 'discord_guild_link_token_generated')
        ->assertSessionHas('flash_data.discord_guild_link_token.token')
        ->assertSessionHas('flash_data.discord_guild_link_token.expires_at');

    $token = $response->baseResponse->getSession()->get('flash_data')['discord_guild_link_token']['token'];

    expect($group->fresh()->discord_link_token_hash)->toBe(hash('sha256', $token))
        ->and($group->fresh()->discord_link_token_expires_at)->not->toBeNull();
});

it('requires guild write scope to link a discord guild through the integration api', function () {
    $token = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($token)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_RUNS_READ,
            ],
        ]);

    $this->postJson(route('api.integrations.discord-guilds.link'), [
        'discord_guild_id' => '123456789012345678',
        'token' => 'ANY-TOKEN',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('links an installed discord guild to the group matching the generated token', function () {
    $apiToken = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($apiToken)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_GUILDS_WRITE,
            ],
        ]);

    $group = Group::factory()->create([
        'name' => 'Token Group',
        'discord_link_token_hash' => hash('sha256', 'TOKEN-1234'),
        'discord_link_token_expires_at' => now()->addMinutes(10),
    ]);
    $integration = DiscordGuildIntegration::query()->create([
        'discord_guild_id' => '223456789012345678',
        'installed_by_discord_user_id' => '323456789012345678',
        'guild_installed_at' => now()->subMinute(),
    ]);

    $this->postJson(route('api.integrations.discord-guilds.link'), [
        'discord_guild_id' => '223456789012345678',
        'token' => 'TOKEN-1234',
        'name' => 'Raid Server',
        'icon_url' => 'https://cdn.discordapp.com/icons/223456789012345678/icon.png',
        'permissions' => '123456',
    ], [
        'Authorization' => 'Bearer '.$apiToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.linked', true)
        ->assertJsonPath('data.group.slug', $group->slug)
        ->assertJsonPath('data.guild.discord_guild_id', '223456789012345678')
        ->assertJsonPath('data.guild.name', 'Raid Server');

    expect($integration->fresh()->group_id)->toBe($group->id)
        ->and($integration->fresh()->name)->toBe('Raid Server')
        ->and($group->fresh()->discord_link_token_hash)->toBeNull()
        ->and($group->fresh()->discord_link_token_expires_at)->toBeNull();
});

it('rejects expired or unknown discord guild link tokens', function () {
    $apiToken = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($apiToken)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_GUILDS_WRITE,
            ],
        ]);

    Group::factory()->create([
        'discord_link_token_hash' => hash('sha256', 'EXPIRED-TOKEN'),
        'discord_link_token_expires_at' => now()->subMinute(),
    ]);

    $this->postJson(route('api.integrations.discord-guilds.link'), [
        'discord_guild_id' => '423456789012345678',
        'token' => 'EXPIRED-TOKEN',
    ], [
        'Authorization' => 'Bearer '.$apiToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('does not let a discord guild link to multiple groups', function () {
    $apiToken = IntegrationClient::makePlainApiToken();
    IntegrationClient::factory()
        ->withApiToken($apiToken)
        ->create([
            'scopes' => [
                IntegrationClient::SCOPE_GUILDS_WRITE,
            ],
        ]);

    $firstGroup = Group::factory()->create();
    $secondGroup = Group::factory()->create([
        'discord_link_token_hash' => hash('sha256', 'SECOND-TOKEN'),
        'discord_link_token_expires_at' => now()->addMinutes(10),
    ]);
    DiscordGuildIntegration::query()->create([
        'group_id' => $firstGroup->id,
        'discord_guild_id' => '523456789012345678',
        'guild_installed_at' => now(),
    ]);

    $this->postJson(route('api.integrations.discord-guilds.link'), [
        'discord_guild_id' => '523456789012345678',
        'token' => 'SECOND-TOKEN',
    ], [
        'Authorization' => 'Bearer '.$apiToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('discord_guild_id');

    expect($secondGroup->fresh()->activeDiscordGuildIntegration)->toBeNull();
});
