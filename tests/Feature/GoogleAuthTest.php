<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});
