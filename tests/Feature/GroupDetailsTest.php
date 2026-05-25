<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns discovery details for visible groups', function () {
    $user = User::factory()->create();
    $group = Group::factory()->public()->create([
        'name' => 'Detail Group',
        'slug' => 'detailgrp',
        'description' => 'Detailed description',
        'recruiting_status' => 'applications_open',
        'primary_focuses' => ['progression', 'maps'],
        'experience_expectation' => 'midcore',
        'voice_expectation' => 'preferred',
        'preferred_languages' => ['en', 'de'],
        'tags' => ['Late Night', 'Blind Prog'],
        'active_timezone' => 'Europe/London',
        'active_days' => ['wed', 'fri'],
        'active_start_time' => '19:00:00',
        'active_end_time' => '22:00:00',
    ]);

    $response = $this->actingAs($user)->getJson(route('groups.details', $group));

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Detail Group')
        ->assertJsonPath('data.region', $group->inferredRegion())
        ->assertJsonPath('data.recruiting_status', 'applications_open')
        ->assertJsonPath('data.primary_focuses.0', 'progression')
        ->assertJsonPath('data.experience_expectation', 'midcore')
        ->assertJsonPath('data.voice_expectation', 'preferred')
        ->assertJsonPath('data.preferred_languages.0', 'en')
        ->assertJsonPath('data.tags.0', 'Late Night')
        ->assertJsonPath('data.links.dashboard', null)
        ->assertJsonPath('data.stats.member_count', 1);
});

it('does not expose discovery details for hidden groups', function () {
    $user = User::factory()->create();
    $group = Group::factory()->hidden()->create([
        'slug' => 'hiddenone',
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.details', $group))
        ->assertNotFound();
});
