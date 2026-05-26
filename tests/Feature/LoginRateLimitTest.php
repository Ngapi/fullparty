<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throttles repeated password login attempts by email and IP address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'target@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), [
        'email' => 'target@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
