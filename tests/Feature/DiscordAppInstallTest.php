<?php

use App\Models\AuditLog;
use App\Models\DiscordGuildIntegration;
use App\Models\DiscordUserIntegration;
use App\Models\IntegrationClient;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserOnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.discord_app.client_id', 'discord-client-id');
    config()->set('services.discord_app.client_secret', 'discord-client-secret');
    config()->set('services.discord_app.user_redirect', 'http://fullparty.test/auth/discord-app/user/callback');
    config()->set('services.discord_app.guild_redirect', 'http://fullparty.test/auth/discord-app/guild/callback');
    config()->set('services.discord_app.guild_install_permissions', '123456');
});

it('generates a separate user install authorization url', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->get(route('discord-app.user.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('https://discord.com/oauth2/authorize')
        ->and($location)->toContain('scope=identify%20applications.commands')
        ->and($location)->toContain('integration_type=1')
        ->and($location)->toContain('redirect_uri=http%3A%2F%2Ffullparty.test%2Fauth%2Fdiscord-app%2Fuser%2Fcallback');
});

it('stores a user install from the discord app callback and advances onboarding', function () {
    $user = User::factory()->create();

    $user->onboardingState()->create([
        'current_step' => UserOnboardingState::STEP_DISCORD,
    ]);

    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response([
            'access_token' => 'discord-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-123',
            'username' => 'raider',
            'global_name' => 'Raid Friend',
            'avatar' => 'avatar-hash',
        ]),
    ]);

    $this
        ->actingAs($user)
        ->withSession([
            'discord_app_user_install_state' => 'state-token',
            'discord_app_user_install_return_to' => route('dashboard'),
        ])
        ->get(route('discord-app.user.callback', [
            'code' => 'oauth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', ['discord_app_user_installed']);

    $integration = DiscordUserIntegration::query()->sole();

    expect($integration->user_id)->toBe($user->id)
        ->and($integration->discord_user_id)->toBe('discord-user-123')
        ->and($integration->username)->toBe('raider')
        ->and($integration->global_name)->toBe('Raid Friend')
        ->and($integration->user_app_installed_at)->not->toBeNull()
        ->and($user->onboardingState()->sole()->current_step)->toBe(UserOnboardingState::STEP_NOTIFICATIONS);

    expect(AuditLog::query()->where('action', 'user.discord_app.user_installed')->exists())->toBeTrue();
});

it('sends a signed discord bot event after a user installs the app', function () {
    $user = User::factory()->create([
        'name' => 'Giki Chomusuke',
    ]);

    IntegrationClient::factory()->create([
        'name' => 'FullParty Discord Bot',
        'type' => IntegrationClient::TYPE_DISCORD_BOT,
        'status' => IntegrationClient::STATUS_ACTIVE,
        'outbound_events_url' => 'https://discord-bot.fullparty.test/events',
        'webhook_signing_secret' => 'integration-secret',
        'allowed_events' => [
            IntegrationClient::EVENT_DISCORD_USER_APP_INSTALLED,
        ],
    ]);

    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response([
            'access_token' => 'discord-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-123',
            'username' => 'raider',
            'global_name' => 'Raid Friend',
            'avatar' => 'avatar-hash',
        ]),
        'https://discord-bot.fullparty.test/events' => Http::response([], 204),
    ]);

    $this
        ->actingAs($user)
        ->withSession([
            'discord_app_user_install_state' => 'state-token',
            'discord_app_user_install_return_to' => route('dashboard'),
        ])
        ->get(route('discord-app.user.callback', [
            'code' => 'oauth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(route('dashboard'));

    Http::assertSent(function (HttpRequest $request) use ($user) {
        if ($request->url() !== 'https://discord-bot.fullparty.test/events') {
            return false;
        }

        $body = $request->body();
        $timestamp = $request->header('X-FullParty-Timestamp')[0] ?? null;
        $signature = $request->header('X-FullParty-Signature')[0] ?? null;
        $payload = json_decode($body, true);

        expect($payload['event'])->toBe('discord.user_app.installed')
            ->and($payload['integration_client_id'])->toBe(IntegrationClient::query()->sole()->id)
            ->and($payload['data']['user']['id'])->toBe($user->id)
            ->and($payload['data']['user']['name'])->toBe('Giki Chomusuke')
            ->and($payload['data']['discord_user']['id'])->toBe('discord-user-123')
            ->and($payload['data']['discord_user']['username'])->toBe('raider')
            ->and($request->header('X-FullParty-Event')[0] ?? null)->toBe('discord.user_app.installed')
            ->and($request->header('X-FullParty-Delivery')[0] ?? null)->toBe($payload['id']);

        return is_string($timestamp)
            && $signature === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'integration-secret');
    });
});

it('notifies admins when a discord bot event delivery fails', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'system_notice_notifications' => false,
    ]);
    $user = User::factory()->create();
    $client = IntegrationClient::factory()->create([
        'name' => 'FullParty Discord Bot',
        'type' => IntegrationClient::TYPE_DISCORD_BOT,
        'status' => IntegrationClient::STATUS_ACTIVE,
        'outbound_events_url' => 'https://discord-bot.fullparty.test/events',
        'webhook_signing_secret' => 'integration-secret',
        'allowed_events' => [
            IntegrationClient::EVENT_DISCORD_USER_APP_INSTALLED,
        ],
    ]);

    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response([
            'access_token' => 'discord-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-123',
            'username' => 'raider',
        ]),
        'https://discord-bot.fullparty.test/events' => Http::response([
            'message' => 'bot is down',
        ], 503),
    ]);

    $this
        ->actingAs($user)
        ->withSession([
            'discord_app_user_install_state' => 'state-token',
            'discord_app_user_install_return_to' => route('dashboard'),
        ])
        ->get(route('discord-app.user.callback', [
            'code' => 'oauth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(route('dashboard'));

    $client->refresh();

    expect($client->last_event_failed_at)->not->toBeNull()
        ->and($client->last_event_error)->not->toBeNull();

    $event = NotificationEvent::query()->where('type', 'integration.event_delivery_failed')->sole();

    expect($event->is_mandatory)->toBeTrue()
        ->and($event->title_key)->toBe('notifications.integrations.delivery_failed.title')
        ->and($event->body_key)->toBe('notifications.integrations.delivery_failed.body')
        ->and($event->message_params['client'])->toBe('FullParty Discord Bot')
        ->and($event->message_params['event'])->toBe(IntegrationClient::EVENT_DISCORD_USER_APP_INSTALLED)
        ->and($event->action_url)->toBe(route('admin.integrations.index'));

    expect(UserNotification::query()
        ->where('notification_event_id', $event->id)
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});

it('refuses to attach a discord user install already linked to another user', function () {
    $existingUser = User::factory()->create();
    $user = User::factory()->create();

    DiscordUserIntegration::query()->create([
        'user_id' => $existingUser->id,
        'discord_user_id' => 'discord-user-123',
        'username' => 'already-linked',
        'user_app_installed_at' => now(),
    ]);

    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response([
            'access_token' => 'discord-access-token',
        ]),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-123',
            'username' => 'raider',
        ]),
    ]);

    $this
        ->actingAs($user)
        ->withSession([
            'discord_app_user_install_state' => 'state-token',
            'discord_app_user_install_return_to' => route('dashboard'),
        ])
        ->get(route('discord-app.user.callback', [
            'code' => 'oauth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['error' => 'discord_app_already_linked']);

    expect(DiscordUserIntegration::query()->where('user_id', $user->id)->doesntExist())->toBeTrue();
});

it('generates a separate guild install authorization url', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->get(route('discord-app.guild.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('https://discord.com/oauth2/authorize')
        ->and($location)->toContain('scope=identify%20bot%20applications.commands')
        ->and($location)->toContain('integration_type=0')
        ->and($location)->toContain('permissions=123456');
});

it('stores a guild install from the discord app callback', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response([
            'access_token' => 'discord-access-token',
        ]),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-installer-123',
            'username' => 'leader',
        ]),
    ]);

    $this
        ->actingAs($user)
        ->withSession([
            'discord_app_guild_install_state' => 'state-token',
            'discord_app_guild_install_return_to' => route('dashboard'),
        ])
        ->get(route('discord-app.guild.callback', [
            'code' => 'oauth-code',
            'state' => 'state-token',
            'guild_id' => 'guild-123',
            'permissions' => '123456',
        ]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', ['discord_app_guild_installed']);

    $integration = DiscordGuildIntegration::query()->sole();

    expect($integration->discord_guild_id)->toBe('guild-123')
        ->and($integration->installed_by_user_id)->toBe($user->id)
        ->and($integration->installed_by_discord_user_id)->toBe('discord-installer-123')
        ->and($integration->permissions)->toBe('123456')
        ->and($integration->guild_installed_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'user.discord_app.guild_installed')->exists())->toBeTrue();
});
