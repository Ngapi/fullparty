<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupMembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function membershipApplicationGroupPayload(string $slug, string $joinMode = Group::JOIN_MODE_APPLICATION, string $groupType = Group::TYPE_COMMUNITY): array
{
    return [
        'name' => 'Application Group',
        'description' => 'A group that reviews members.',
        'profile_picture' => null,
        'banner_image' => null,
        'discord_invite_url' => null,
        'datacenter' => config('datacenters.values')[0] ?? 'Light',
        'join_mode' => $joinMode,
        'is_visible' => true,
        'slug' => $slug,
        'group_type' => $groupType,
        'primary_focuses' => [config('group_discovery.primary_focuses')[0] ?? 'progression'],
        'experience_expectation' => config('group_discovery.experience_expectations')[0] ?? 'casual',
        'voice_expectation' => config('group_discovery.voice_expectations')[0] ?? 'optional',
        'preferred_languages' => [config('group_discovery.preferred_languages')[0] ?? 'en'],
        'tags' => [],
        'active_timezone' => null,
        'active_days' => [],
        'active_start_time' => null,
        'active_end_time' => null,
    ];
}

function membershipApplicationSettingsPayload(Group $group, string $joinMode): array
{
    return [
        'name' => $group->name,
        'description' => $group->description,
        'profile_picture' => null,
        'banner_image' => null,
        'discord_invite_url' => $group->discord_invite_url,
        'datacenter' => $group->datacenter,
        'join_mode' => $joinMode,
        'is_visible' => $group->is_visible,
    ];
}

function membershipApplicationSchemaPayload(): array
{
    return [[
        'id' => 'intro',
        'type' => 'small_text',
        'name' => [
            'en' => 'Tell us about yourself',
            'de' => '',
            'fr' => '',
            'ja' => '',
        ],
        'description' => [
            'en' => 'A short intro is enough.',
            'de' => '',
            'fr' => '',
            'ja' => '',
        ],
        'required' => true,
        'options' => [],
    ], [
        'id' => 'favorite_role',
        'type' => 'select',
        'name' => [
            'en' => 'Favorite role',
            'de' => '',
            'fr' => '',
            'ja' => '',
        ],
        'description' => [],
        'required' => true,
        'options' => [[
            'id' => 'tank',
            'label' => [
                'en' => 'Tank',
                'de' => '',
                'fr' => '',
                'ja' => '',
            ],
        ], [
            'id' => 'healer',
            'label' => [
                'en' => 'Healer',
                'de' => '',
                'fr' => '',
                'ja' => '',
            ],
        ]],
    ], [
        'id' => 'are_you_a_gamer',
        'type' => 'toggle',
        'name' => [
            'en' => 'Are you a gamer?',
            'de' => '',
            'fr' => '',
            'ja' => '',
        ],
        'description' => [],
        'required' => true,
        'options' => [],
    ]];
}

it('creates the default membership application form for application based groups', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('groups.store'), membershipApplicationGroupPayload('appform'))
        ->assertRedirect();

    $group = Group::query()->where('slug', 'appform')->firstOrFail();

    expect($group->join_mode)->toBe(Group::JOIN_MODE_APPLICATION)
        ->and($group->membership_application_schema)->toHaveCount(1)
        ->and($group->membership_application_schema[0]['type'])->toBe('toggle')
        ->and($group->membership_application_schema[0]['name']['en'])->toBe('Are you a gamer?');
});

it('generates the default form when a group is changed to application based', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->inviteOnly()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => null,
    ]);

    $this->actingAs($owner)
        ->put(route('groups.dashboard.settings.update', $group), membershipApplicationSettingsPayload($group, Group::JOIN_MODE_APPLICATION))
        ->assertRedirect();

    $group->refresh();

    expect($group->join_mode)->toBe(Group::JOIN_MODE_APPLICATION)
        ->and($group->membership_application_schema)->toHaveCount(1)
        ->and($group->membership_application_schema[0]['name']['en'])->toBe('Are you a gamer?');
});

it('allows admins and owners, but not moderators, to update the membership application form', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $moderator = User::factory()->create();
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $admin->id,
        'role' => GroupMembership::ROLE_ADMIN,
        'joined_at' => now(),
    ]);
    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $this->actingAs($moderator)
        ->put(route('groups.dashboard.membership-application-form.update', $group), [
            'fields' => membershipApplicationSchemaPayload(),
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('groups.dashboard.membership-application-form.update', $group), [
            'fields' => membershipApplicationSchemaPayload(),
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $group->refresh();

    expect($group->membership_application_schema)->toHaveCount(3)
        ->and($group->membership_application_schema[0]['id'])->toBe('intro');
});

it('hides membership application dashboard permissions when the group is not application based', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->inviteOnly()->create([
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('groups.dashboard', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.permissions.can_review_membership_applications', false)
            ->where('group.permissions.can_manage_membership_application_form', false)
        );

    $this->actingAs($owner)
        ->get(route('groups.dashboard.membership-applications.index', $group))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('groups.dashboard.membership-application-form.edit', $group))
        ->assertNotFound();
});

it('submits membership applications and snapshots the current form schema', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => membershipApplicationSchemaPayload(),
    ]);

    $this->actingAs($applicant)
        ->get(route('groups.membership-applications.create', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MembershipApplications/Create')
            ->has('formSchema', 3)
            ->where('existingApplication', null)
        );

    $this->actingAs($applicant)
        ->post(route('groups.membership-applications.store', $group), [
            'answers' => [
                'intro' => 'I like structured groups.',
                'favorite_role' => 'tank',
                'are_you_a_gamer' => false,
            ],
        ])
        ->assertRedirect(route('groups.membership-applications.create', $group))
        ->assertSessionDoesntHaveErrors();

    $application = GroupMembershipApplication::query()->where('group_id', $group->id)->firstOrFail();

    $group->update([
        'membership_application_schema' => [[
            'id' => 'new_question',
            'type' => 'small_text',
            'name' => ['en' => 'New question'],
            'description' => [],
            'required' => true,
            'options' => [],
        ]],
    ]);

    expect($application->answers['intro'])->toBe('I like structured groups.')
        ->and($application->answers['are_you_a_gamer'])->toBeFalse()
        ->and($application->form_snapshot)->toHaveCount(3)
        ->and($application->form_snapshot[0]['id'])->toBe('intro')
        ->and($group->memberships()->where('user_id', $applicant->id)->exists())->toBeFalse();
});

it('requires required answers and blocks duplicate pending applications', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => membershipApplicationSchemaPayload(),
    ]);

    $this->actingAs($applicant)
        ->from(route('groups.membership-applications.create', $group))
        ->post(route('groups.membership-applications.store', $group), [
            'answers' => [
                'intro' => '',
                'favorite_role' => 'tank',
                'are_you_a_gamer' => true,
            ],
        ])
        ->assertRedirect(route('groups.membership-applications.create', $group))
        ->assertSessionHasErrors('answers.intro');

    $this->actingAs($applicant)
        ->post(route('groups.membership-applications.store', $group), [
            'answers' => [
                'intro' => 'Ready to join.',
                'favorite_role' => 'tank',
                'are_you_a_gamer' => true,
            ],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($applicant)
        ->from(route('groups.membership-applications.create', $group))
        ->post(route('groups.membership-applications.store', $group), [
            'answers' => [
                'intro' => 'Trying again.',
                'favorite_role' => 'healer',
                'are_you_a_gamer' => true,
            ],
        ])
        ->assertRedirect(route('groups.membership-applications.create', $group))
        ->assertSessionHasErrors('application');

    expect(GroupMembershipApplication::query()->where('group_id', $group->id)->count())->toBe(1);
});

it('rejects membership application free text answers above one thousand characters', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $schema = [[
        'id' => 'intro',
        'type' => 'small_text',
        'name' => ['en' => 'Short intro'],
        'description' => [],
        'required' => true,
        'options' => [],
    ], [
        'id' => 'details',
        'type' => 'big_text',
        'name' => ['en' => 'More detail'],
        'description' => [],
        'required' => true,
        'options' => [],
    ], [
        'id' => 'are_you_a_gamer',
        'type' => 'toggle',
        'name' => ['en' => 'Are you a gamer?'],
        'description' => [],
        'required' => true,
        'options' => [],
    ]];
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => $schema,
    ]);

    $this->actingAs($applicant)
        ->from(route('groups.membership-applications.create', $group))
        ->post(route('groups.membership-applications.store', $group), [
            'answers' => [
                'intro' => str_repeat('a', 1001),
                'details' => str_repeat('b', 1001),
                'are_you_a_gamer' => true,
            ],
        ])
        ->assertRedirect(route('groups.membership-applications.create', $group))
        ->assertSessionHasErrors([
            'answers.intro' => 'This answer must be 1000 characters or fewer.',
            'answers.details' => 'This answer must be 1000 characters or fewer.',
        ]);
});

it('lets applicants update pending group requests', function () {
    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => membershipApplicationSchemaPayload(),
    ]);

    $application = GroupMembershipApplication::factory()->create([
        'group_id' => $group->id,
        'user_id' => $applicant->id,
        'answers' => [
            'intro' => 'Original intro.',
            'favorite_role' => 'tank',
            'are_you_a_gamer' => true,
        ],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);

    $this->actingAs($applicant)
        ->get(route('groups.membership-applications.create', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/MembershipApplications/Create')
            ->where('existingApplication.id', $application->id)
            ->where('existingApplication.answers.intro', 'Original intro.')
        );

    $this->actingAs($applicant)
        ->put(route('groups.membership-applications.update', $group), [
            'answers' => [
                'intro' => 'Updated intro.',
                'favorite_role' => 'healer',
                'are_you_a_gamer' => false,
            ],
        ])
        ->assertRedirect(route('groups.membership-applications.create', $group))
        ->assertSessionDoesntHaveErrors();

    $application->refresh();

    expect($application->answers['intro'])->toBe('Updated intro.')
        ->and($application->answers['favorite_role'])->toBe('healer')
        ->and($application->answers['are_you_a_gamer'])->toBeFalse();
});

it('shows the current users group requests', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $activeGroup = Group::factory()->applicationBased()->create([
        'name' => 'Active Request Group',
    ]);
    $reviewedGroup = Group::factory()->applicationBased()->create([
        'name' => 'Reviewed Request Group',
    ]);
    $otherGroup = Group::factory()->applicationBased()->create([
        'name' => 'Other Request Group',
    ]);

    $activeRequest = GroupMembershipApplication::factory()->create([
        'group_id' => $activeGroup->id,
        'user_id' => $user->id,
        'status' => GroupMembershipApplication::STATUS_PENDING,
        'answers' => ['intro' => 'Still waiting.'],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);
    $reviewedRequest = GroupMembershipApplication::factory()->declined()->create([
        'group_id' => $reviewedGroup->id,
        'user_id' => $user->id,
        'answers' => ['intro' => 'Not this time.'],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);
    GroupMembershipApplication::factory()->create([
        'group_id' => $otherGroup->id,
        'user_id' => $otherUser->id,
        'answers' => ['intro' => 'Not mine.'],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);

    $this->actingAs($user)
        ->get(route('groups.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/MembershipRequests/Index')
            ->has('activeRequests', 1)
            ->where('activeRequests.0.id', $activeRequest->id)
            ->where('activeRequests.0.can_edit', true)
            ->where('activeRequests.0.group.name', 'Active Request Group')
            ->has('historicalRequests', 1)
            ->where('historicalRequests.0.id', $reviewedRequest->id)
            ->where('historicalRequests.0.group.name', 'Reviewed Request Group')
        );
});

it('lets moderators approve and decline membership applications', function () {
    $owner = User::factory()->create();
    $moderator = User::factory()->create();
    $approvedApplicant = User::factory()->create();
    $declinedApplicant = User::factory()->create();
    $group = Group::factory()->applicationBased()->create([
        'owner_id' => $owner->id,
        'membership_application_schema' => membershipApplicationSchemaPayload(),
    ]);

    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $approvedApplication = GroupMembershipApplication::factory()->create([
        'group_id' => $group->id,
        'user_id' => $approvedApplicant->id,
        'answers' => [
            'intro' => 'Approve me.',
            'favorite_role' => 'tank',
            'are_you_a_gamer' => true,
        ],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);
    $declinedApplication = GroupMembershipApplication::factory()->create([
        'group_id' => $group->id,
        'user_id' => $declinedApplicant->id,
        'answers' => [
            'intro' => 'Maybe not.',
            'favorite_role' => 'healer',
            'are_you_a_gamer' => true,
        ],
        'form_snapshot' => membershipApplicationSchemaPayload(),
    ]);

    $this->actingAs($moderator)
        ->get(route('groups.dashboard.membership-applications.index', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/MembershipApplications/Index')
            ->has('applications', 2)
        );

    $this->actingAs($moderator)
        ->post(route('groups.dashboard.membership-applications.approve', [$group, $approvedApplication]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($moderator)
        ->post(route('groups.dashboard.membership-applications.decline', [$group, $declinedApplication]), [
            'review_reason' => 'Not a fit right now.',
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($approvedApplication->fresh()->status)->toBe(GroupMembershipApplication::STATUS_APPROVED)
        ->and($declinedApplication->fresh()->status)->toBe(GroupMembershipApplication::STATUS_DECLINED)
        ->and($declinedApplication->fresh()->review_reason)->toBe('Not a fit right now.')
        ->and($group->memberships()->where('user_id', $approvedApplicant->id)->exists())->toBeTrue()
        ->and($group->memberships()->where('user_id', $declinedApplicant->id)->exists())->toBeFalse();
});
