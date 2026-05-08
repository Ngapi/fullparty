<?php

namespace App\Services\Groups;

use App\Models\ActivityTypeVersion;
use App\Models\CharacterClass;
use App\Models\PhantomJob;

class ActivityRosterSummaryPresetBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(?ActivityTypeVersion $activityTypeVersion): array
    {
        if (!$activityTypeVersion) {
            return [];
        }

        $groupLabelsByKey = collect($activityTypeVersion->layout_schema['groups'] ?? [])
            ->filter(fn (mixed $group) => is_array($group) && filled($group['key'] ?? null))
            ->mapWithKeys(fn (array $group) => [
                (string) $group['key'] => is_array($group['label'] ?? null)
                    ? $group['label']
                    : ['en' => (string) $group['key']],
            ])
            ->all();

        return collect($activityTypeVersion->roster_summary_presets ?? [])
            ->filter(fn (mixed $preset) => is_array($preset) && filled($preset['key'] ?? null))
            ->map(function (array $preset) use ($groupLabelsByKey) {
                return [
                    'key' => (string) $preset['key'],
                    'label' => is_array($preset['label'] ?? null)
                        ? $preset['label']
                        : ['en' => (string) $preset['key']],
                    'description' => is_array($preset['description'] ?? null)
                        ? $preset['description']
                        : ['en' => ''],
                    'requirements' => collect($preset['requirements'] ?? [])
                        ->filter(fn (mixed $requirement) => is_array($requirement))
                        ->map(function (array $requirement) use ($groupLabelsByKey) {
                            $source = (string) ($requirement['source'] ?? '');
                            $sourceId = (int) ($requirement['source_id'] ?? 0);
                            $scopeGroupKeys = collect($requirement['scope_group_keys'] ?? [])
                                ->filter(fn (mixed $groupKey) => is_string($groupKey) && filled($groupKey))
                                ->map(fn (string $groupKey) => trim($groupKey))
                                ->values()
                                ->all();

                            return [
                                'source' => $source,
                                'source_id' => $sourceId,
                                'comparison' => (string) ($requirement['comparison'] ?? 'at_least'),
                                'target_count' => (int) ($requirement['target_count'] ?? 1),
                                'scope_type' => (string) ($requirement['scope_type'] ?? 'all_slots'),
                                'scope_group_keys' => $scopeGroupKeys,
                                'scope_groups' => collect($scopeGroupKeys)
                                    ->map(fn (string $groupKey) => [
                                        'key' => $groupKey,
                                        'label' => $groupLabelsByKey[$groupKey] ?? ['en' => $groupKey],
                                    ])
                                    ->values()
                                    ->all(),
                                'item' => $this->resolveItem($source, $sourceId),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveItem(string $source, int $sourceId): array
    {
        return match ($source) {
            'character_classes' => $this->resolveCharacterClassItem($sourceId),
            'phantom_jobs' => $this->resolvePhantomJobItem($sourceId),
            default => [
                'id' => $sourceId,
                'label' => ['en' => (string) $sourceId],
                'meta' => null,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCharacterClassItem(int $sourceId): array
    {
        $characterClass = CharacterClass::query()->find($sourceId);

        if (!$characterClass) {
            return [
                'id' => $sourceId,
                'label' => ['en' => (string) $sourceId],
                'meta' => null,
            ];
        }

        return [
            'id' => $characterClass->id,
            'label' => ['en' => $characterClass->name],
            'meta' => [
                'role' => $characterClass->role,
                'shorthand' => $characterClass->shorthand,
                'icon_url' => $characterClass->icon_url,
                'flaticon_url' => $characterClass->flaticon_url,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePhantomJobItem(int $sourceId): array
    {
        $phantomJob = PhantomJob::query()->find($sourceId);

        if (!$phantomJob) {
            return [
                'id' => $sourceId,
                'label' => ['en' => (string) $sourceId],
                'meta' => null,
            ];
        }

        return [
            'id' => $phantomJob->id,
            'label' => ['en' => $phantomJob->name],
            'meta' => [
                'icon_url' => $phantomJob->icon_url,
                'black_icon_url' => $phantomJob->black_icon_url,
                'transparent_icon_url' => $phantomJob->transparent_icon_url,
                'sprite_url' => $phantomJob->sprite_url,
            ],
        ];
    }
}
