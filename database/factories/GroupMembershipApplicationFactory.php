<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMembershipApplication;
use App\Models\User;
use App\Services\Groups\MembershipApplicationFormSchemaService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMembershipApplication>
 */
class GroupMembershipApplicationFactory extends Factory
{
    protected $model = GroupMembershipApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schema = app(MembershipApplicationFormSchemaService::class)->defaultSchema();

        return [
            'group_id' => Group::factory()->applicationBased(),
            'user_id' => User::factory(),
            'status' => GroupMembershipApplication::STATUS_PENDING,
            'answers' => [
                'are_you_a_gamer' => true,
            ],
            'form_snapshot' => $schema,
            'submitted_at' => now(),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_reason' => null,
        ];
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GroupMembershipApplication::STATUS_APPROVED,
            'reviewed_by_user_id' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function declined(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GroupMembershipApplication::STATUS_DECLINED,
            'reviewed_by_user_id' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now(),
            'review_reason' => fake()->sentence(),
        ]);
    }
}
