<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects the naked home route to the localized home route', function () {
    $this->get('/')
        ->assertRedirect('/en');
});

it('redirects the naked login route to the localized login route', function () {
    $this->get('/auth/login')
        ->assertRedirect('/en/auth/login');
});

it('renders the localized login route with the requested locale and ziggy defaults', function () {
    $response = $this->get('/de/auth/login');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('locale.current', 'de')
        )
        ->assertSee('<html lang="de" class="dark">', false);
});
