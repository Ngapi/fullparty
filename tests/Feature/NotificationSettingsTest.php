<?php

use App\Models\AuditLog;
use App\Models\DiscordUserIntegration;
use App\Models\IntegrationClient;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationPreferenceSettingsService;
use App\Support\Notifications\NotificationCategory;
use App\Support\Notifications\NotificationPreferenceChannel;
use App\Support\Notifications\NotificationTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('uses enabled defaults for notification categories while optional system notices and off site channels stay off', function () {
    $user = User::query()->forceCreate([
        'name' => 'Settings Tester',
        'email' => 'settings@example.com',
        'password' => 'password',
    ])->fresh();

    expect($user->application_notifications)->toBeTrue()
        ->and($user->run_and_reminder_notifications)->toBeTrue()
        ->and($user->group_update_notifications)->toBeTrue()
        ->and($user->assignment_notifications)->toBeTrue()
        ->and($user->account_character_notifications)->toBeTrue()
        ->and($user->system_notice_notifications)->toBeFalse()
        ->and($user->email_notifications)->toBeFalse()
        ->and($user->discord_notifications)->toBeFalse();
});

it('defaults supported discord topics on when the discord app is installed', function () {
    $user = User::factory()->create([
        'application_notifications' => true,
        'run_and_reminder_notifications' => true,
        'assignment_notifications' => true,
        'account_character_notifications' => true,
        'system_notice_notifications' => false,
        'discord_notifications' => false,
    ]);

    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-defaults-123',
        'username' => 'Defaults Tester',
        'user_app_installed_at' => now(),
    ]);

    $preferences = app(NotificationPreferenceSettingsService::class)
        ->serializeUserPreferences($user->fresh(['discordUserIntegration']));

    expect($preferences[NotificationTopic::APPLICATIONS_SUBMITTED][NotificationPreferenceChannel::DISCORD])->toBeTrue()
        ->and($preferences[NotificationTopic::ASSIGNMENTS_ROSTER][NotificationPreferenceChannel::DISCORD])->toBeTrue()
        ->and($preferences[NotificationTopic::RUNS_REMINDERS][NotificationPreferenceChannel::DISCORD])->toBeTrue()
        ->and($preferences[NotificationTopic::CHARACTER_CHANGES][NotificationPreferenceChannel::DISCORD])->toBeTrue()
        ->and($preferences[NotificationTopic::SYSTEM_MAINTENANCE][NotificationPreferenceChannel::DISCORD])->toBeFalse();
});

it('updates the new notification category preferences and delivery channels', function () {
    $user = User::factory()->create([
        'application_notifications' => true,
        'run_and_reminder_notifications' => true,
        'group_update_notifications' => true,
        'assignment_notifications' => true,
        'account_character_notifications' => true,
        'system_notice_notifications' => false,
        'email_notifications' => false,
        'discord_notifications' => false,
    ]);

    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-123',
        'username' => 'Settings Tester',
        'user_app_installed_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('settings.notifications'), [
        'application_notifications' => false,
        'run_and_reminder_notifications' => false,
        'group_update_notifications' => false,
        'assignment_notifications' => false,
        'account_character_notifications' => false,
        'system_notice_notifications' => true,
        'email_notifications' => true,
        'discord_notifications' => true,
    ]);

    $response
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', ['notification_settings_updated']);

    $user->refresh();

    expect($user->application_notifications)->toBeFalse()
        ->and($user->run_and_reminder_notifications)->toBeFalse()
        ->and($user->group_update_notifications)->toBeFalse()
        ->and($user->assignment_notifications)->toBeFalse()
        ->and($user->account_character_notifications)->toBeFalse()
        ->and($user->system_notice_notifications)->toBeTrue()
        ->and($user->email_notifications)->toBeTrue()
        ->and($user->discord_notifications)->toBeTrue();

    $auditLog = AuditLog::query()->where('action', 'user.settings.notifications_updated')->sole();

    expect($auditLog->actor_user_id)->toBe($user->id)
        ->and($auditLog->metadata['changes']['run_and_reminder_notifications']['old'])->toBeTrue()
        ->and($auditLog->metadata['changes']['run_and_reminder_notifications']['new'])->toBeFalse()
        ->and($auditLog->metadata['changes']['system_notice_notifications']['old'])->toBeFalse()
        ->and($auditLog->metadata['changes']['system_notice_notifications']['new'])->toBeTrue();

    $event = NotificationEvent::query()->where('type', 'user.settings.notifications_updated')->sole();

    expect($event->category)->toBe(NotificationCategory::ACCOUNT_CHARACTER_UPDATES)
        ->and($event->is_mandatory)->toBeFalse()
        ->and($event->actor_user_id)->toBe($user->id)
        ->and($event->subject_type)->toBe(User::class)
        ->and($event->subject_id)->toBe($user->id)
        ->and($event->title_key)->toBe('notifications.user.settings.notifications_updated.title')
        ->and($event->body_key)->toBe('notifications.user.settings.notifications_updated.body')
        ->and($event->action_url)->toBe(route('settings'))
        ->and($event->message_params['changed_category_label_keys'])->toBe([
            'settings.notifications.applications',
            'settings.notifications.runs_and_reminders',
            'settings.notifications.group_updates',
            'settings.notifications.assignments',
            'settings.notifications.account_character_updates',
            'settings.notifications.system_notices',
        ])
        ->and($event->message_params['changed_channel_label_keys'])->toBe([
            'settings.notifications.email_notifications',
            'settings.notifications.discord_notifications',
        ])
        ->and($event->message_params['changed_setting_label_keys'])->toBe([
            'settings.notifications.applications',
            'settings.notifications.runs_and_reminders',
            'settings.notifications.group_updates',
            'settings.notifications.assignments',
            'settings.notifications.account_character_updates',
            'settings.notifications.system_notices',
            'settings.notifications.email_notifications',
            'settings.notifications.discord_notifications',
        ]);

    expect(UserNotification::query()->where('notification_event_id', $event->id)->exists())->toBeFalse()
        ->and($user->fresh()->inAppNotifications)->toHaveCount(0);
});

it('stores granular notification preferences per topic and channel', function () {
    $user = User::factory()->create([
        'run_and_reminder_notifications' => true,
        'group_update_notifications' => true,
        'email_notifications' => true,
        'discord_notifications' => false,
    ]);

    $this->actingAs($user)
        ->post(route('settings.notifications'), [
            'application_notifications' => true,
            'run_and_reminder_notifications' => true,
            'group_update_notifications' => true,
            'assignment_notifications' => true,
            'account_character_notifications' => true,
            'system_notice_notifications' => true,
            'email_notifications' => true,
            'discord_notifications' => false,
            'notification_preferences' => [
                NotificationTopic::RUNS_REMINDERS => [
                    NotificationPreferenceChannel::IN_APP => false,
                    NotificationPreferenceChannel::EMAIL => true,
                    NotificationPreferenceChannel::DISCORD => false,
                ],
                NotificationTopic::GROUP_RUN_POSTS => [
                    NotificationPreferenceChannel::IN_APP => false,
                ],
            ],
        ])
        ->assertRedirect(route('settings'));

    $this->assertDatabaseHas('user_notification_preferences', [
        'user_id' => $user->id,
        'topic' => NotificationTopic::RUNS_REMINDERS,
        'channel' => NotificationPreferenceChannel::IN_APP,
        'enabled' => false,
    ]);

    $this->assertDatabaseHas('user_notification_preferences', [
        'user_id' => $user->id,
        'topic' => NotificationTopic::RUNS_REMINDERS,
        'channel' => NotificationPreferenceChannel::EMAIL,
        'enabled' => true,
    ]);

    $this->assertDatabaseHas('user_notification_preferences', [
        'user_id' => $user->id,
        'topic' => NotificationTopic::GROUP_RUN_POSTS,
        'channel' => NotificationPreferenceChannel::IN_APP,
        'enabled' => false,
    ]);
});

it('forces discord notifications off when the user has not installed the discord app', function () {
    $user = User::factory()->create([
        'discord_notifications' => false,
    ]);

    $this->actingAs($user);

    $this->post(route('settings.notifications'), [
        'application_notifications' => true,
        'run_and_reminder_notifications' => true,
        'group_update_notifications' => true,
        'assignment_notifications' => true,
        'account_character_notifications' => true,
        'system_notice_notifications' => false,
        'email_notifications' => true,
        'discord_notifications' => true,
    ])->assertRedirect(route('settings'));

    expect($user->fresh()->discord_notifications)->toBeFalse();
});

it('generates a short lived discord user link token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.discord-integration.link-token'))
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', ['discord_user_link_token_generated'])
        ->assertSessionHas('flash_data.discord_user_link_token.token')
        ->assertSessionHas('flash_data.discord_user_link_token.expires_at');

    $token = session('flash_data.discord_user_link_token.token');

    $user->refresh();

    expect($token)->toBeString()
        ->and($user->discord_link_token_hash)->toBe(hash('sha256', $token))
        ->and($user->discord_link_token_expires_at)->not->toBeNull()
        ->and($user->discord_link_token_expires_at->isFuture())->toBeTrue();
});

it('disconnects the discord app, disables discord notifications, and notifies the bot', function () {
    $user = User::factory()->create([
        'discord_notifications' => true,
    ]);

    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-123',
        'username' => 'Settings Tester',
        'global_name' => 'Settings Friend',
        'user_app_installed_at' => now(),
    ]);

    IntegrationClient::factory()->create([
        'type' => IntegrationClient::TYPE_DISCORD_BOT,
        'status' => IntegrationClient::STATUS_ACTIVE,
        'outbound_events_url' => 'https://discord-bot.fullparty.test/events',
        'webhook_signing_secret' => 'integration-secret',
        'allowed_events' => [
            IntegrationClient::EVENT_DISCORD_USER_APP_DISCONNECTED,
        ],
    ]);

    Http::fake([
        'https://discord-bot.fullparty.test/events' => Http::response([], 204),
    ]);

    $this->actingAs($user)
        ->delete(route('settings.discord-integration.destroy'))
        ->assertRedirect(route('settings'))
        ->assertSessionHas('success', ['discord_integration_disconnected']);

    $integration = DiscordUserIntegration::query()->sole();

    expect($integration->revoked_at)->not->toBeNull()
        ->and($user->fresh()->discord_notifications)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.discord_app.disconnected')->exists())->toBeTrue();

    Http::assertSent(function (HttpRequest $request) {
        if ($request->url() !== 'https://discord-bot.fullparty.test/events') {
            return false;
        }

        $body = $request->body();
        $timestamp = $request->header('X-FullParty-Timestamp')[0] ?? null;
        $signature = $request->header('X-FullParty-Signature')[0] ?? null;
        $payload = json_decode($body, true);

        expect($payload['event'])->toBe(IntegrationClient::EVENT_DISCORD_USER_APP_DISCONNECTED)
            ->and($payload['data']['discord_user']['id'])->toBe('discord-123')
            ->and($payload['data']['message'])->toContain('FullParty has disconnected the integration');

        return is_string($timestamp)
            && $signature === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'integration-secret');
    });
});
