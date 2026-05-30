<?php

namespace App\Support\Activities;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityTypeVersion;

class ActivityDisplayName
{
    public static function for(?Activity $activity, string $fallback = 'Activity'): string
    {
        if (! $activity instanceof Activity) {
            return $fallback;
        }

        if (filled($activity->title)) {
            return (string) $activity->title;
        }

        $activity->loadMissing(['activityTypeVersion', 'activityType']);

        $activityTypeName = self::localizedLabel(
            $activity->activityTypeVersion?->name
                ?? $activity->activityType?->draft_name
        );

        if (filled($activityTypeName)) {
            return $activityTypeName;
        }

        $activityTypeVersion = $activity->activity_type_version_id
            ? ActivityTypeVersion::query()->find($activity->activity_type_version_id)
            : null;
        $activityTypeName = self::localizedLabel($activityTypeVersion?->name);

        if (filled($activityTypeName)) {
            return $activityTypeName;
        }

        $activityType = $activity->activity_type_id
            ? ActivityType::query()->find($activity->activity_type_id)
            : null;
        $activityTypeName = self::localizedLabel($activityType?->draft_name);

        if (filled($activityTypeName)) {
            return $activityTypeName;
        }

        return sprintf('Activity #%d', $activity->id);
    }

    private static function localizedLabel(mixed $label): ?string
    {
        if (is_string($label) && filled($label)) {
            return $label;
        }

        if (! is_array($label)) {
            return null;
        }

        $englishLabel = $label['en'] ?? null;

        if (is_string($englishLabel) && filled($englishLabel)) {
            return $englishLabel;
        }

        $firstLabel = collect($label)
            ->first(fn ($value) => is_string($value) && filled($value));

        return is_string($firstLabel) ? $firstLabel : null;
    }
}
