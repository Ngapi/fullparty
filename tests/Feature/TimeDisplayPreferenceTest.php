<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists the authenticated users time display preference', function () {
    $user = User::factory()->create([
        'time_display_mode' => User::TIME_DISPLAY_LOCAL,
    ]);

    $this->actingAs($user)
        ->patchJson(route('settings.time-display'), [
            'time_display_mode' => User::TIME_DISPLAY_SERVER,
        ])
        ->assertOk()
        ->assertJsonPath('time_display_mode', User::TIME_DISPLAY_SERVER);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'time_display_mode' => User::TIME_DISPLAY_SERVER,
    ]);
});

it('rejects unsupported time display preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('settings.time-display'), [
            'time_display_mode' => 'moon',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('time_display_mode');
});
