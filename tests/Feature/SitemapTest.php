<?php

use App\Models\Activity;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders localized public urls in the sitemap and excludes non-indexable activity urls', function () {
    $publicGroup = Group::factory()->open()->create([
        'slug' => 'publicgp',
    ]);

    $publicActivity = Activity::factory()->create([
        'group_id' => $publicGroup->id,
        'status' => Activity::STATUS_SCHEDULED,
        'is_public' => true,
    ]);

    $plannedActivity = Activity::factory()->create([
        'group_id' => $publicGroup->id,
        'status' => Activity::STATUS_PLANNED,
        'is_public' => true,
    ]);

    $privateActivity = Activity::factory()->private()->create([
        'group_id' => $publicGroup->id,
        'status' => Activity::STATUS_SCHEDULED,
    ]);

    $response = $this->get('/sitemap.xml');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('home', ['locale' => 'en']), false)
        ->assertSee(route('legal.privacy', ['locale' => 'en']), false)
        ->assertSee(route('groups.activities.overview', [
            'locale' => 'en',
            'group' => $publicGroup,
            'activity' => $publicActivity,
        ]), false)
        ->assertDontSee(route('groups.activities.overview', [
            'locale' => 'en',
            'group' => $publicGroup,
            'activity' => $plannedActivity,
        ]), false)
        ->assertDontSee(route('groups.activities.overview', [
            'locale' => 'en',
            'group' => $publicGroup,
            'activity' => $privateActivity,
        ]), false);
});
