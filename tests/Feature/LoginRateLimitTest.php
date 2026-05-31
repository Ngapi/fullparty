<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in with either email or username', function (string $login) {
    $user = User::factory()->create([
        'name' => 'RaidLeader',
        'email' => 'leader@example.com',
    ]);

    $this->post(route('login.store'), [
        'login' => $login,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with([
    'email' => ['leader@example.com'],
    'username' => ['RaidLeader'],
    'case-insensitive username' => ['raidleader'],
]);

it('throttles repeated password login attempts by identifier and IP address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), [
            'login' => 'target@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');
    }

    $this->post(route('login.store'), [
        'login' => 'target@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
