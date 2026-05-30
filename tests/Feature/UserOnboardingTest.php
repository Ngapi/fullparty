<?php

use App\Models\DiscordUserIntegration;
use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks jumping to notification setup before the discord step has been reached', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('onboarding.update'), [
            'current_step' => UserOnboardingState::STEP_NOTIFICATIONS,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_step']);
});

it('allows users to skip discord after seeing the warning step', function () {
    $user = User::factory()->create([
        'notification_preferences_reviewed_at' => null,
    ]);

    $this->actingAs($user);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_DISCORD,
    ])->assertOk();

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_DISCORD_WARNING,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_DISCORD_WARNING);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_NOTIFICATIONS,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_NOTIFICATIONS)
        ->assertJsonPath('onboarding.discord_skipped_at', fn (?string $value) => $value !== null);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_NEXT,
        'notification_preferences_reviewed' => true,
    ])->assertOk();

    $this->postJson(route('onboarding.complete'))
        ->assertOk()
        ->assertJsonPath('onboarding.required', false);

    $state = $user->onboardingState()->sole();

    expect($state->discord_skipped_at)->not->toBeNull()
        ->and($state->completed_at)->not->toBeNull();
});

it('persists the required onboarding flow through discord app install and completion', function () {
    $user = User::factory()->create([
        'notification_preferences_reviewed_at' => null,
    ]);

    $this->actingAs($user);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_DISCORD,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_DISCORD)
        ->assertJsonPath('onboarding.required', true);

    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-123',
        'username' => 'tester',
        'user_app_installed_at' => now(),
    ]);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_NOTIFICATIONS,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_NOTIFICATIONS);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_NEXT,
        'notification_preferences_reviewed' => true,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_NEXT);

    $this->postJson(route('onboarding.complete'))
        ->assertOk()
        ->assertJsonPath('onboarding.required', false);

    $state = $user->onboardingState()->sole();

    expect($state->discord_skipped_at)->toBeNull()
        ->and($state->notification_preferences_completed_at)->not->toBeNull()
        ->and($state->completed_at)->not->toBeNull()
        ->and($user->fresh()->notification_preferences_reviewed_at)->not->toBeNull();
});

it('allows the discord step to continue when discord is connected', function () {
    $user = User::factory()->create();

    DiscordUserIntegration::query()->create([
        'user_id' => $user->id,
        'discord_user_id' => 'discord-123',
        'username' => 'Discord Tester',
        'user_app_installed_at' => now(),
    ]);

    $this->actingAs($user);

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_DISCORD,
    ])->assertOk();

    $this->patchJson(route('onboarding.update'), [
        'current_step' => UserOnboardingState::STEP_NOTIFICATIONS,
    ])
        ->assertOk()
        ->assertJsonPath('onboarding.current_step', UserOnboardingState::STEP_NOTIFICATIONS);
});

it('does not complete onboarding before the final step is reached', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('onboarding.complete'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_step']);
});
