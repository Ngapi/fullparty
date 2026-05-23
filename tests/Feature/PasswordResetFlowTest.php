<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('renders the forgot password page for guests', function () {
    $this->get(route('password.request'))
        ->assertOk();
});

it('sends a password reset notification for a known email and returns a generic success response', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'RESET@example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('success', ['password_reset_link_sent']);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns the same generic success response for an unknown email address', function () {
    Notification::fake();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('success', ['password_reset_link_sent']);

    Notification::assertNothingSent();
});

it('renders the reset password page with the supplied token and email', function () {
    $this->get(route('password.reset', [
        'token' => 'example-token',
        'email' => 'user@example.com',
    ]))
        ->assertOk();
});

it('resets the password for a valid token', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'RESET@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', ['password_reset']);

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('OldPassword123!', $user->fresh()->password))->toBeFalse();
});

it('rejects an invalid password reset token', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    $this->from(route('password.reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
    ]))
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('password.reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
        ]))
        ->assertSessionHasErrors(['email']);
});
