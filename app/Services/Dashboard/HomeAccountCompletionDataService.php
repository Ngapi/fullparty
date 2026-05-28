<?php

namespace App\Services\Dashboard;

use App\Models\User;

final class HomeAccountCompletionDataService
{
    private const IMPORTANT = 'important';

    private const RECOMMENDED = 'recommended';

    /**
     * @return array{
     *     percent: int,
     *     completed_count: int,
     *     total_count: int,
     *     should_celebrate_completion: bool,
     *     items: array<int, array{key: string, priority: string, is_complete: bool}>
     * }
     */
    public function forUser(User $user): array
    {
        $items = [
            $this->item('email_verified', self::IMPORTANT, $user->email_verified_at !== null),
            $this->item('verified_character', self::IMPORTANT, $user->characters()->exists()),
            $this->item('primary_character', self::IMPORTANT, $user->characters()->where('is_primary', true)->exists()),
            $this->item('joined_group', self::IMPORTANT, $this->hasGroup($user)),
            $this->item('connected_discord', self::RECOMMENDED, $user->socialAccounts()->where('provider', 'discord')->exists()),
            $this->item('notification_preferences_reviewed', self::RECOMMENDED, $user->notification_preferences_reviewed_at !== null),
        ];

        $completedCount = collect($items)
            ->filter(fn (array $item): bool => $item['is_complete'])
            ->count();
        $totalCount = count($items);
        $isComplete = $completedCount === $totalCount;
        $shouldCelebrateCompletion = $isComplete && $user->account_completion_celebrated_at === null;

        if ($shouldCelebrateCompletion) {
            $user->forceFill([
                'account_completion_celebrated_at' => now(),
            ])->save();
        }

        return [
            'percent' => $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'should_celebrate_completion' => $shouldCelebrateCompletion,
            'items' => $items,
        ];
    }

    /**
     * @return array{key: string, priority: string, is_complete: bool}
     */
    private function item(string $key, string $priority, bool $isComplete): array
    {
        return [
            'key' => $key,
            'priority' => $priority,
            'is_complete' => $isComplete,
        ];
    }

    private function hasGroup(User $user): bool
    {
        return $user->ownedGroups()->exists()
            || $user->groupMemberships()->exists();
    }
}
