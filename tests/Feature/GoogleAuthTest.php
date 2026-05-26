<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('stores long google oauth tokens during the callback flow', function () {
    $googleUser = (new SocialiteUser)
        ->map([
            'id' => 'google-user-123',
            'name' => 'Michaela Pferdefuß',
            'email' => 'harapekobuono@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/ACg8ocJ2hCZr5W7kRrJ3NMFvIrw_pwQpPtmgJaPWHb2F5PxvPovfsRM=s96-c',
            'nickname' => null,
        ])
        ->setRaw([
            'name' => 'Michaela Pferdefuß',
            'nickname' => null,
            'avatar' => 'https://lh3.googleusercontent.com/a/ACg8ocJ2hCZr5W7kRrJ3NMFvIrw_pwQpPtmgJaPWHb2F5PxvPovfsRM=s96-c',
            'email_verified' => true,
        ])
        ->setToken(str_repeat('a', 1024))
        ->setRefreshToken(str_repeat('b', 1024))
        ->setExpiresIn(3600);

    $provider = Mockery::mock();
    $provider->shouldReceive('user')
        ->once()
        ->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'harapekobuono@gmail.com')->first();

    expect($user)->not->toBeNull();

    $account = SocialAccount::query()
        ->where('provider', 'google')
        ->where('provider_user_id', 'google-user-123')
        ->first();

    expect($account)->not->toBeNull()
        ->and($account->user_id)->toBe($user->id)
        ->and($account->access_token)->toBe(str_repeat('a', 1024))
        ->and($account->refresh_token)->toBe(str_repeat('b', 1024));

    $storedAccount = DB::table('social_accounts')->where('id', $account->id)->first();

    expect($storedAccount->access_token)->not->toBe(str_repeat('a', 1024))
        ->and($storedAccount->refresh_token)->not->toBe(str_repeat('b', 1024));
});

it('can refresh an existing google social account even when legacy token columns are unreadable', function () {
    $user = User::factory()->create([
        'email' => 'harapekobuono@gmail.com',
    ]);

    $socialAccountId = DB::table('social_accounts')->insertGetId([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
        'provider_name' => 'Old Google User',
        'provider_email' => 'harapekobuono@gmail.com',
        'avatar_url' => 'https://example.com/old-avatar.png',
        'access_token' => 'legacy-plaintext-token',
        'refresh_token' => 'legacy-plaintext-refresh',
        'provider_data' => json_encode(['name' => 'Old Google User']),
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $googleUser = (new SocialiteUser)
        ->map([
            'id' => 'google-user-123',
            'name' => 'Michaela Pferdefuß',
            'email' => 'harapekobuono@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/ACg8ocJ2hCZr5W7kRrJ3NMFvIrw_pwQpPtmgJaPWHb2F5PxvPovfsRM=s96-c',
            'nickname' => null,
        ])
        ->setRaw([
            'name' => 'Michaela Pferdefuß',
            'nickname' => null,
            'avatar' => 'https://lh3.googleusercontent.com/a/ACg8ocJ2hCZr5W7kRrJ3NMFvIrw_pwQpPtmgJaPWHb2F5PxvPovfsRM=s96-c',
            'email_verified' => true,
        ])
        ->setToken(str_repeat('c', 1024))
        ->setRefreshToken(str_repeat('d', 1024))
        ->setExpiresIn(3600);

    $provider = Mockery::mock();
    $provider->shouldReceive('user')
        ->once()
        ->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($provider);

    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('dashboard'));

    $account = SocialAccount::query()->findOrFail($socialAccountId);

    expect($account->user_id)->toBe($user->id)
        ->and($account->access_token)->toBe(str_repeat('c', 1024))
        ->and($account->refresh_token)->toBe(str_repeat('d', 1024));
});

it('rejects google callbacks when the provider email is not verified', function () {
    User::factory()->create([
        'email' => 'victim@example.com',
    ]);

    fakeSocialiteUser('google', (new SocialiteUser)
        ->map([
            'id' => 'attacker-google',
            'name' => 'Attacker',
            'email' => 'victim@example.com',
            'avatar' => null,
            'nickname' => null,
        ])
        ->setRaw([
            'email_verified' => false,
        ]));

    $response = $this->get(route('google.callback'));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.social_email_unverified')]);

    $this->assertGuest();

    expect(SocialAccount::query()->where('provider', 'google')->doesntExist())->toBeTrue();
});

it('rejects discord callbacks when the provider email is not verified', function () {
    User::factory()->create([
        'email' => 'victim@example.com',
    ]);

    fakeSocialiteUser('discord', (new SocialiteUser)
        ->map([
            'id' => 'attacker-discord',
            'name' => 'Attacker',
            'email' => 'victim@example.com',
            'avatar' => null,
            'nickname' => 'attacker',
        ])
        ->setRaw([
            'verified' => false,
        ]));

    $response = $this->get(route('discord.callback'));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.social_email_unverified')]);

    $this->assertGuest();

    expect(SocialAccount::query()->where('provider', 'discord')->doesntExist())->toBeTrue();
});

it('rejects xivauth callbacks when the provider email is not verified', function () {
    User::factory()->create([
        'email' => 'victim@example.com',
    ]);

    $xivauthUser = (new SocialiteUser)
        ->map([
            'id' => 'attacker-xivauth',
            'name' => null,
            'email' => 'victim@example.com',
            'email_verified' => false,
        ])
        ->setRaw([
            'user' => [
                'email_verified' => false,
            ],
            'characters' => [],
        ]);

    $provider = Mockery::mock();
    $provider->shouldReceive('enablePKCE')
        ->once()
        ->andReturnSelf();
    $provider->shouldReceive('user')
        ->once()
        ->andReturn($xivauthUser);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('xivauth')
        ->andReturn($provider);

    $response = $this->get(route('xivauth.callback'));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.social_email_unverified')]);

    $this->assertGuest();

    expect(SocialAccount::query()->where('provider', 'xivauth')->doesntExist())->toBeTrue();
});

it('does not expose oauth secrets in shared inertia user props', function () {
    $user = User::factory()->create();

    $user->socialAccounts()->create([
        'provider' => 'discord',
        'provider_user_id' => 'discord-secret-id',
        'provider_name' => 'Discord User',
        'provider_email' => 'discord@example.com',
        'access_token' => 'secret-access-token',
        'refresh_token' => 'secret-refresh-token',
        'provider_data' => [
            'verified' => true,
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('settings'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.social_accounts.0.provider', 'discord')
            ->where('auth.user.social_accounts.0.provider_name', 'Discord User')
            ->missing('auth.user.social_accounts.0.provider_user_id')
            ->missing('auth.user.social_accounts.0.access_token')
            ->missing('auth.user.social_accounts.0.refresh_token')
            ->missing('auth.user.social_accounts.0.provider_data')
        );
});

it('can share social account summaries without decrypting legacy token columns', function () {
    $user = User::factory()->create();

    DB::table('social_accounts')->insert([
        'user_id' => $user->id,
        'provider' => 'discord',
        'provider_user_id' => 'discord-secret-id',
        'provider_name' => 'Discord User',
        'provider_email' => 'discord@example.com',
        'avatar_url' => 'https://example.com/discord-avatar.png',
        'access_token' => 'legacy-plaintext-token',
        'refresh_token' => 'legacy-plaintext-refresh',
        'provider_data' => json_encode([
            'verified' => true,
        ]),
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('settings'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.social_accounts.0.provider', 'discord')
            ->where('auth.user.social_accounts.0.provider_name', 'Discord User')
            ->missing('auth.user.social_accounts.0.provider_user_id')
            ->missing('auth.user.social_accounts.0.access_token')
            ->missing('auth.user.social_accounts.0.refresh_token')
            ->missing('auth.user.social_accounts.0.provider_data')
        );
});

function fakeSocialiteUser(string $providerName, SocialiteUser $user): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('user')
        ->once()
        ->andReturn($user);

    Socialite::shouldReceive('driver')
        ->once()
        ->with($providerName)
        ->andReturn($provider);
}
