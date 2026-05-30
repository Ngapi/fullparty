<?php

namespace Database\Factories;

use App\Models\FeaturedGroup;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeaturedGroup>
 */
class FeaturedGroupFactory extends Factory
{
    protected $model = FeaturedGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'created_by_user_id' => User::factory(),
            'priority' => fake()->numberBetween(0, 100),
            'starts_at' => null,
            'ends_at' => null,
            'internal_note' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
