<?php

use App\Models\ActivityApplication;
use App\Models\AuditLog;
use App\Models\Character;
use App\Models\DiscordUserIntegration;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('anonymizes the account while preserving history-bearing records', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'public_profile' => true,
        'public_characters' => true,
        'email_notifications' => true,
        'discord_notifications' => true,
    ]);

    $group = Group::factory()->create();

    $group->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'discord',
        'provider_user_id' => 'discord-123',
    ]);
    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-123',
        'user_app_installed_at' => now(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'session-delete-test',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    $character = Character::factory()->primary()->create([
        'user_id' => $user->id,
        'name' => 'History Character',
    ]);

    $application = ActivityApplication::factory()->create([
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
    ]);

    $this->actingAs($user)
        ->delete(route('settings.account.destroy'))
        ->assertRedirect('/');

    $this->assertGuest();

    $user->refresh();
    $character->refresh();
    $application->refresh();

    expect($user->name)->toBe('Deleted User #'.$user->id)
        ->and($user->email)->not->toBe('test@example.com')
        ->and($user->avatar_url)->toBeNull()
        ->and($user->public_profile)->toBeFalse()
        ->and($user->public_characters)->toBeFalse()
        ->and($user->email_notifications)->toBeFalse()
        ->and($user->discord_notifications)->toBeFalse();

    expect($character->user_id)->toBe($user->id)
        ->and($application->user_id)->toBe($user->id);

    expect($group->memberships()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DiscordUserIntegration::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'test@example.com')->exists())->toBeFalse();

    $auditLog = AuditLog::query()->where('action', 'user.account.deleted')->sole();

    expect($auditLog->actor_user_id)->toBe($user->id)
        ->and($auditLog->scope_id)->toBe($user->id);
});

it('blocks account deletion while the user still owns groups', function () {
    $user = User::factory()->create();
    Group::factory()->create([
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('settings'))
        ->delete(route('settings.account.destroy'))
        ->assertRedirect(route('settings'))
        ->assertSessionHasErrors([
            'error' => 'account_delete_group_owner',
        ]);

    $user->refresh();

    expect($user->name)->not->toBe('Deleted User #'.$user->id);
    expect(AuditLog::query()->where('action', 'user.account.deleted')->exists())->toBeFalse();
});
